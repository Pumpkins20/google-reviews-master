<?php

namespace App\Services\Ai;

use App\Models\PhotoAnalysis;
use App\Models\PlacePhoto;
use App\Models\Store;
use App\Support\PhoneNumbers;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads a store photo with Gemini and decides whether it carries contact
 * details that do not belong to the store.
 *
 * Gemini only reads what is in the picture (phone numbers with their role and
 * whether they look pasted on, QR codes, social accounts). Whether a
 * number is official is decided here, against the store's registered numbers
 * and the company-wide numbers, so the verdict does not depend on the model and
 * no official number has to be sent in the prompt (every token is billed).
 */
class PhotoClassifier
{
    // Gemini's short answer codes -> the category names stored in photo_analyses.
    const CATEGORY_MAP = [
        'PLAIN' => 'FOTO_TOKO_NORMAL',
        'CONTACT' => 'ADA_KONTAK',
        'PROMO' => 'PROMOSI_ASING',
        'IRRELEVANT' => 'TIDAK_RELEVAN',
        'UNREADABLE' => 'TIDAK_TERBACA',
    ];
    const ROLES = ['SIGN', 'COMPLAINT', 'PACKAGING', 'OVERLAY', 'OTHER'];
    const ROLE_LABELS = [
        'SIGN' => 'Nomor tercetak langsung pada papan, spanduk, banner atau identitas toko',
        'COMPLAINT' => 'Nomor yang secara eksplisit digunakan untuk kritik dan saran',
        'PACKAGING' => 'Nomor yang tercetak pada kemasan produk',
        'OVERLAY' => 'Nomor pada stiker, kertas, atau tulisan yang tampak ditambahkan di atas objek/foto',
        'OTHER' => 'Nomor yang tidak dapat dikategorikan',
    ];
    const MAX_IMAGE_BYTES = 6 * 1024 * 1024;

    /**
     * Analyse one photo and store the result (including its cost).
     *
     * @param  \App\Models\PlacePhoto  $photo
     * @return \App\Models\PhotoAnalysis
     *
     * @throws \Exception  When the image cannot be fetched or Gemini fails
     */
    public function classify(PlacePhoto $photo)
    {
        $photo->loadMissing('place');
        $store = $this->storeFor($photo);
        $storeNumbers = PhoneNumbers::officialNumbers($store ? [$store->telp, $store->whatsapp, $store->nohp_delivery] : []);
        $companyNumbers = $this->companyNumbers();

        [$mime, $imageBytes] = $this->downloadImage($photo);
        $this->throttle();
        $started = microtime(true);
        $gemini = $this->askGemini($mime, $imageBytes);
        $elapsedMs = (int) round((microtime(true) - $started) * 1000);

        $verdict = $this->verdict($gemini['data'], $storeNumbers, $companyNumbers);

        $analysis = PhotoAnalysis::create([
            'photo_id' => $photo->id,
            'provider' => 'gemini',
            'model' => config('photo_ai.model'),
            'prompt_version' => config('photo_ai.prompt_version'),
            'label' => $verdict['label'],
            'category' => $gemini['data']['category'],
            'confidence' => null,
            'extracted_phones' => $verdict['phones'],
            'official_phones' => array_values(array_unique(array_merge($storeNumbers, $companyNumbers))),
            'phone_details' => $verdict['details'],
            'phone_mismatch' => $verdict['phone_mismatch'],
            'flags' => $verdict['flags'],
            'reason' => $verdict['reason'],
            'raw_response' => json_encode($gemini['raw'], JSON_UNESCAPED_UNICODE),
            'execution_time_ms' => $elapsedMs,
            'input_tokens' => $gemini['input_tokens'],
            'output_tokens' => $gemini['output_tokens'],
            'cost' => $gemini['cost'],
            'analyzed_at' => now(),
        ]);

        $photo->update(['label' => $verdict['label'], 'analyzed_at' => $analysis->analyzed_at]);

        return $analysis;
    }

    /**
     * Pure decision step: every number read from the photo is compared with the
     * store's numbers and the company-wide numbers.
     *
     *   official number (store or company)               -> ok
     *   foreign number on product packaging              -> ignored (unless it looks pasted on)
     *   near an official number                          -> suspicious (scam if it looks pasted on)
     *   foreign number, digits unclear                   -> suspicious
     *   foreign number, unclear context (role OTHER)     -> suspicious (scam if pasted on / promo photo)
     *   any other foreign number (sign, banner, pasted   -> scam
     *     over the store photo, replacing the complaints
     *     line, ...)
     *
     * Impostors paste their own number over the store's or company's number or hang a
     * banner over the storefront photo, so a foreign number in those places is a scam.
     *
     * @param  array  $data  Normalised Gemini answer: phones[{number, role, edited, clear}], qr, handles, category
     * @param  array  $storeNumbers  Canonical numbers registered for this store
     * @param  array  $companyNumbers  Canonical company-wide numbers
     * @return array  label, phones, phone_mismatch, flags, reason, details
     */
    public function verdict(array $data, array $storeNumbers, array $companyNumbers)
    {
        $official = array_merge($storeNumbers, $companyNumbers);
        // Without any number registered for the store nothing can be called foreign;
        // such a store's own number would look foreign, so it only becomes "suspicious".
        $canJudge = (bool) $storeNumbers;

        $flags = [
            'qr_code' => !empty($data['qr']),
            'social_handles' => $this->foreign((array) ($data['handles'] ?? []), config('photo_ai.official_handles')),
            'promo_category' => ($data['category'] ?? '') === 'PROMOSI_ASING',
            'unverifiable_phones' => [],
        ];

        $details = [];
        $scam = [];
        $suspicious = [];
        $unverifiable = [];

        foreach ((array) ($data['phones'] ?? []) as $phone) {
            $number = trim((string) ($phone['number'] ?? ''));
            if (!PhoneNumbers::isComparable($number)) {
                continue;
            }
            $role = in_array($phone['role'] ?? '', self::ROLES, true) ? $phone['role'] : 'OTHER';
            $edited = !empty($phone['edited']);
            $clear = !array_key_exists('clear', $phone) || (bool) $phone['clear'];

            $detail = ['number' => $number, 'role' => $role, 'edited' => $edited, 'clear' => $clear,
                'status' => 'foreign', 'source' => null, 'nearest' => null, 'distance' => null];

            if (PhoneNumbers::matches($number, $companyNumbers)) {
                $detail['status'] = 'official';
                $detail['source'] = 'company';
            } elseif (PhoneNumbers::matches($number, $storeNumbers)) {
                $detail['status'] = 'official';
                $detail['source'] = 'store';
            } elseif ($role === 'PACKAGING' && !$edited) {
                $detail['status'] = 'ignored';
            } elseif ($near = PhoneNumbers::near($number, $official)) {
                $detail['status'] = 'near';
                $detail['nearest'] = PhoneNumbers::display($near[0]);
                $detail['distance'] = $near[1];
                if ($edited) {
                    $scam[] = "{$number} mirip nomor resmi {$detail['nearest']} dan tampak ditempel";
                } else {
                    $suspicious[] = "{$number} mirip nomor resmi {$detail['nearest']} (selisih {$near[1]} digit), kemungkinan salah baca atau diubah; cek manual";
                }
            } elseif (!$canJudge) {
                $unverifiable[] = $number;
            } elseif (!$clear) {
                $suspicious[] = "{$number} tidak terdaftar tetapi digitnya kurang jelas; cek manual";
            } elseif ($role === 'OTHER' && !$edited && !$flags['promo_category']) {
                $suspicious[] = "{$number} tidak terdaftar dan konteksnya tidak jelas; cek manual";
            } else {
                $scam[] = "{$number} tidak terdaftar (" . self::ROLE_LABELS[$role]
                    . ($edited ? ', tampak ditempel' : '')
                    . ($flags['promo_category'] ? ', foto berisi promosi bisnis lain' : '') . ')';
            }

            $details[] = $detail;
        }

        $flags['unverifiable_phones'] = $unverifiable;

        $notes = [];
        if ($flags['qr_code']) {
            $notes[] = 'Ada QR code';
        }
        if ($flags['social_handles']) {
            $notes[] = 'Akun sosial: ' . implode(', ', $flags['social_handles']);
        }
        if ($flags['promo_category']) {
            $notes[] = 'Promosi bisnis lain';
        }
        if ($unverifiable) {
            $notes[] = 'Toko belum punya nomor terdaftar, nomor ' . implode(', ', $unverifiable) . ' tidak bisa diverifikasi';
        }

        if ($scam) {
            $label = 'scam';
        } elseif ($suspicious || $notes) {
            $label = 'suspicious';
        } elseif (($data['category'] ?? '') === 'TIDAK_TERBACA') {
            $label = 'unreadable';
        } else {
            $label = 'ok';
        }

        $reasons = array_merge($scam, $suspicious, $notes);
        if (!$reasons) {
            $reasons[] = $label === 'unreadable'
                ? 'Teks pada foto tidak terbaca'
                : (collect($details)->contains('status', 'official') ? 'Nomor di foto sesuai nomor resmi' : 'Tidak ada tanda mencurigakan');
        }

        return [
            'label' => $label,
            'phones' => array_column($details, 'number'),
            // A number that is neither official nor ignorable was seen, whatever the verdict.
            'phone_mismatch' => collect($details)->whereIn('status', ['foreign', 'near'])->isNotEmpty(),
            'flags' => $flags,
            'reason' => implode('. ', $reasons) . '.',
            'details' => $details,
        ];
    }

    /**
     * Re-apply the current verdict rules to a stored analysis without calling Gemini.
     * Possible for analyses that kept their per-number details (prompt v2 onwards).
     *
     * @param  \App\Models\PhotoAnalysis  $analysis
     * @return bool  false when the analysis predates per-number details
     */
    public function recompute(PhotoAnalysis $analysis)
    {
        if ($analysis->phone_details === null) {
            return false;
        }

        $photo = $analysis->photo()->with('place')->first();
        $store = $photo ? $this->storeFor($photo) : null;
        $storeNumbers = PhoneNumbers::officialNumbers($store ? [$store->telp, $store->whatsapp, $store->nohp_delivery] : []);
        $companyNumbers = $this->companyNumbers();
        $flags = $analysis->flags ?: [];

        $verdict = $this->verdict([
            'phones' => array_map(function ($d) {
                return ['number' => $d['number'], 'role' => $d['role'], 'edited' => $d['edited'], 'clear' => $d['clear']];
            }, (array) $analysis->phone_details),
            'qr' => !empty($flags['qr_code']),
            'handles' => (array) ($flags['social_handles'] ?? []),
            'category' => $analysis->category,
        ], $storeNumbers, $companyNumbers);

        $analysis->update([
            'label' => $verdict['label'],
            'extracted_phones' => $verdict['phones'],
            'official_phones' => array_values(array_unique(array_merge($storeNumbers, $companyNumbers))),
            'phone_details' => $verdict['details'],
            'phone_mismatch' => $verdict['phone_mismatch'],
            'flags' => $verdict['flags'],
            'reason' => $verdict['reason'],
        ]);

        // The photo shows its latest analysis only.
        if ($photo && $photo->latestAnalysis && $photo->latestAnalysis->id === $analysis->id) {
            $photo->update(['label' => $verdict['label']]);
        }

        return true;
    }

    /**
     * Canonical company-wide official numbers from config.
     *
     * @return array
     */
    public function companyNumbers()
    {
        return PhoneNumbers::officialNumbers((array) config('photo_ai.company_phones'));
    }

    /**
     * Items that do not contain any of the allowed fragments.
     *
     * @param  array  $items
     * @param  array  $allowed
     * @return array
     */
    protected function foreign(array $items, array $allowed)
    {
        return array_values(array_filter($items, function ($item) use ($allowed) {
            foreach ($allowed as $fragment) {
                if ($fragment !== '' && stripos((string) $item, $fragment) !== false) {
                    return false;
                }
            }

            return trim((string) $item) !== '';
        }));
    }

    /** @var float  Time of the previous Gemini call in this process */
    protected static $lastCallAt = 0.0;

    /**
     * Keep to PHOTO_AI_RPM requests per minute (the free Gemini tier allows about 15).
     * 0 turns pacing off, for paid tiers with higher limits.
     *
     * @return void
     */
    protected function throttle()
    {
        $rpm = (int) config('photo_ai.requests_per_minute');
        if ($rpm > 0) {
            $wait = (60 / $rpm) - (microtime(true) - self::$lastCallAt);
            if ($wait > 0) {
                usleep((int) ($wait * 1000000));
            }
        }
        self::$lastCallAt = microtime(true);
    }

    protected function storeFor(PlacePhoto $photo)
    {
        $code = $photo->place ? $photo->place->store_code : null;

        return $code ? Store::safe()->where('kode_toko', $code)->first() : null;
    }

    /**
     * @return array  [mime type, bytes]
     */
    protected function downloadImage(PlacePhoto $photo)
    {
        $response = Http::timeout(30)->get($photo->imageUrl('w' . config('photo_ai.image_width')));

        if ($response->status() === 404 || $response->status() === 410) {
            $photo->update(['removed_at' => now()]);
            throw new Exception("Foto sudah tidak tersedia di Google ({$response->status()}).");
        }
        if ($response->failed()) {
            throw new Exception("Gagal mengunduh foto ({$response->status()}).");
        }

        $bytes = $response->body();
        if ($bytes === '' || strlen($bytes) > self::MAX_IMAGE_BYTES) {
            throw new Exception('Ukuran foto tidak valid (' . strlen($bytes) . ' byte).');
        }

        $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));

        return [strpos($mime, 'image/') === 0 ? $mime : 'image/jpeg', $bytes];
    }

    /**
     * The prompt and schema are kept deliberately short: every input token and,
     * at four times the price, every output token is billed per photo.
     *
     * @return array  data (normalised), raw, input_tokens, output_tokens, cost
     */
    protected function askGemini($mime, $imageBytes)
    {
        $apiKey = config('ai.providers.gemini.api_key');
        if (empty($apiKey)) {
            throw new Exception('Gemini API key is not configured.');
        }

        $model = config('photo_ai.model');
        $url = config('ai.providers.gemini.api_url') . $model . ':generateContent?key=' . $apiKey;

        $prompt = "Read the text in this store photo. For each phone number give n (as printed), r (role), "
            . "e (true if the digits look pasted or overlaid, or differ in print from the surrounding text), "
            . "c (true if every digit is clearly legible).\n"
            . "Roles: SIGN=store sign/banner, COMPLAINT=complaints & suggestions line, PACKAGING=printed on product packaging, "
            . "OVERLAY=sticker/paper/text pasted over the image, OTHER.\n"
            . "Also qr (QR code visible), s (social handles), cat (first match): UNREADABLE, IRRELEVANT, "
            . "PROMO (other business ads), CONTACT (contact info visible), PLAIN (store, product or interior).\n"
            . "Never guess unreadable digits. Do not judge authenticity.\n"
            // Without these two lines Gemini indents its JSON (~45% more output tokens) and lists
            // product names as phone numbers (331 output tokens on a shelf photo).
            . "Only real phone numbers (digit sequences), never names. Reply with minified JSON on one line.";

        $payload = [
            'contents' => [[
                'parts' => [
                    ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($imageBytes)]],
                    ['text' => $prompt],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0,
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'p' => ['type' => 'ARRAY', 'items' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'n' => ['type' => 'STRING'],
                                'r' => ['type' => 'STRING', 'enum' => self::ROLES],
                                'e' => ['type' => 'BOOLEAN'],
                                'c' => ['type' => 'BOOLEAN'],
                            ],
                            'required' => ['n', 'r', 'e', 'c'],
                        ]],
                        'qr' => ['type' => 'BOOLEAN'],
                        's' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                        'cat' => ['type' => 'STRING', 'enum' => array_keys(self::CATEGORY_MAP)],
                    ],
                    'required' => ['p', 'qr', 's', 'cat'],
                ],
            ],
        ];
        // Thinking tokens are billed as output; reading text off a photo does not need them.
        if (strpos($model, 'gemini-2.5') === 0) {
            $payload['generationConfig']['thinkingConfig'] = ['thinkingBudget' => 0];
        }

        $response = Http::timeout(60)->post($url, $payload);

        if ($response->status() === 429) {
            throw new Exception('Gemini API Rate Limit exceeded (429): ' . substr((string) $response->json('error.message'), 0, 300));
        }
        if ($response->failed()) {
            throw new Exception('Gemini API call failed: ' . $response->status() . ' - ' . $response->body());
        }

        $raw = $response->json();
        $text = $response->json('candidates.0.content.parts.0.text');
        $answer = $text ? json_decode($text, true) : null;
        if (!is_array($answer)) {
            throw new Exception('Gemini returned no usable answer: ' . substr(json_encode($raw), 0, 400));
        }

        $usage = $raw['usageMetadata'] ?? [];
        $input = (int) ($usage['promptTokenCount'] ?? 0);
        $output = (int) ($usage['candidatesTokenCount'] ?? 0) + (int) ($usage['thoughtsTokenCount'] ?? 0);

        return [
            'data' => $this->normalize($answer),
            'raw' => ['answer' => $answer, 'usage' => $usage],
            'input_tokens' => $input,
            'output_tokens' => $output,
            'cost' => $this->cost($model, $input, $output),
        ];
    }

    /**
     * Gemini's short keys -> the structure verdict() reads.
     *
     * @param  array  $answer
     * @return array
     */
    public function normalize(array $answer)
    {
        $phones = [];
        foreach ((array) ($answer['p'] ?? []) as $p) {
            $phones[] = [
                'number' => (string) ($p['n'] ?? ''),
                'role' => (string) ($p['r'] ?? 'OTHER'),
                'edited' => !empty($p['e']),
                'clear' => !array_key_exists('c', $p) || (bool) $p['c'],
            ];
        }

        return [
            'phones' => $phones,
            'qr' => !empty($answer['qr']),
            'handles' => (array) ($answer['s'] ?? []),
            'category' => self::CATEGORY_MAP[$answer['cat'] ?? ''] ?? 'ADA_KONTAK',
        ];
    }

    /**
     * USD cost from per-million-token prices in config/ai.php; null when the model has no price.
     *
     * @return float|null
     */
    public function cost($model, $inputTokens, $outputTokens)
    {
        // Model names contain dots (gemini-2.5-flash), so config()'s dot notation cannot be used.
        $pricing = (config('ai.pricing') ?: [])[$model] ?? null;
        if (!$pricing) {
            Log::warning("No token pricing configured for model [{$model}]; photo cost not recorded.");

            return null;
        }

        return round((($inputTokens * $pricing['input']) + ($outputTokens * $pricing['output'])) / 1000000, 6);
    }
}
