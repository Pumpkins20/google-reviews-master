<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Review;
use App\Models\ReviewAnalysis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class AnalyzeReviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 30;

    protected $review;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Review $review)
    {
        $this->review = $review;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $reviewText = $this->getReviewText();

        // 1. If the review text is empty (star-only review), skip Gemini and save directly
        if (empty($reviewText)) {
            ReviewAnalysis::updateOrCreate(
                [
                    'review_id' => $this->review->review_id,
                    'place_id' => $this->review->place_id
                ],
                [
                    'provider' => 'system',
                    'model' => 'empty-text-detector',
                    'prompt_version' => config('gemini.prompt_version', 'v1.0'),
                    'spam_label' => 'ham',
                    'confidence' => 1.0,
                    'category' => 'lainnya',
                    'reason' => 'Ulasan hanya berupa rating bintang (tanpa teks)',
                    'raw_response' => json_encode(['system_skipped' => true]),
                    'analyzed_at' => now(),
                ]
            );
            return;
        }

        // 2. Check for API key. If empty, trigger mock analysis for testing
        $apiKey = config('gemini.api_key');
        if (empty($apiKey) || $apiKey === 'your_key_here' || $apiKey === "''") {
            $this->mockAnalysis($reviewText);
            return;
        }

        // 3. Prepare payload and call Gemini API
        $apiUrl = config('gemini.api_url') . '?key=' . $apiKey;
        $prompt = strtr(config('gemini.prompt_template'), [
            '{rating}' => $this->review->rating,
            '{review_text}' => $reviewText,
        ]);

        try {
            $response = Http::timeout(30)->post($apiUrl, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'spam_label' => [
                                'type' => 'STRING',
                                'enum' => ['spam', 'ham']
                            ],
                            'confidence' => [
                                'type' => 'NUMBER'
                            ],
                            'category' => [
                                'type' => 'STRING',
                                'enum' => ['produk', 'layanan', 'fasilitas', 'lainnya']
                            ],
                            'reason' => [
                                'type' => 'STRING'
                            ]
                        ],
                        'required' => ['spam_label', 'confidence', 'category', 'reason']
                    ]
                ]
            ]);

            if ($response->status() === 429) {
                // Rate limit reached - release back to queue with delay
                Log::warning("Gemini API rate limited (429) for review: {$this->review->review_id}. Releasing job.");
                $this->release(60);
                return;
            }

            if ($response->failed()) {
                throw new Exception("Gemini API call failed with status: " . $response->status() . " - " . $response->body());
            }

            $rawResponse = $response->json();
            $textResponse = $response->json('candidates.0.content.parts.0.text');

            if (empty($textResponse)) {
                throw new Exception("Empty content response from Gemini API: " . json_encode($rawResponse));
            }

            $result = json_decode($textResponse, true);

            if (!is_array($result) || !isset($result['spam_label'])) {
                throw new Exception("Invalid JSON structured output from Gemini: " . $textResponse);
            }

            // Save the results
            ReviewAnalysis::updateOrCreate(
                [
                    'review_id' => $this->review->review_id,
                    'place_id' => $this->review->place_id
                ],
                [
                    'provider' => 'gemini',
                    'model' => config('gemini.model'),
                    'prompt_version' => config('gemini.prompt_version', 'v1.0'),
                    'spam_label' => $result['spam_label'],
                    'confidence' => $result['confidence'] ?? 0.5,
                    'category' => $result['category'] ?? 'lainnya',
                    'reason' => $result['reason'] ?? '',
                    'raw_response' => json_encode($rawResponse),
                    'analyzed_at' => now(),
                ]
            );

        } catch (Exception $e) {
            Log::error("Failed to analyze review {$this->review->review_id}: " . $e->getMessage());
            throw $e; // Throw exception to let Laravel retry the job
        }
    }

    /**
     * Extract the review text from JSON or raw string.
     */
    protected function getReviewText()
    {
        $raw = $this->review->review_text;
        if (empty($raw)) {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            // Look for 'id' first, then 'en', then fallback to first element
            if (!empty($decoded['id'])) {
                return $decoded['id'];
            }
            if (!empty($decoded['en'])) {
                return $decoded['en'];
            }
            // Fallback to first non-empty value in array
            foreach ($decoded as $val) {
                if (!empty($val)) {
                    return $val;
                }
            }
            return '';
        }

        return $raw;
    }

    /**
     * Create a mock analysis for local testing when API key is missing.
     */
    protected function mockAnalysis($reviewText)
    {
        $rating = $this->review->rating;

        // Simple heuristic rule to generate varied mock classifications
        $isSpam = 'ham';
        $category = 'lainnya';

        if (strlen($reviewText) < 5) {
            $isSpam = 'spam';
            $reason = 'Teks ulasan terlalu pendek atau tidak bermakna.';
        } elseif (stripos($reviewText, 'promo') !== false || stripos($reviewText, 'diskon') !== false || stripos($reviewText, 'jual') !== false) {
            $isSpam = 'spam';
            $reason = 'Ulasan mengandung kalimat promosi atau jualan.';
        } else {
            $reason = 'Ulasan normal. ';
            if (stripos($reviewText, 'pelayanan') !== false || stripos($reviewText, 'ramah') !== false || stripos($reviewText, 'melayani') !== false || stripos($reviewText, 'karyawan') !== false) {
                $category = 'layanan';
                $reason .= 'Membahas pelayanan staf toko.';
            } elseif (stripos($reviewText, 'cat') !== false || stripos($reviewText, 'warna') !== false || stripos($reviewText, 'kualitas') !== false || stripos($reviewText, 'produk') !== false) {
                $category = 'produk';
                $reason .= 'Membahas produk cat atau kualitas warna.';
            } elseif (stripos($reviewText, 'parkir') !== false || stripos($reviewText, 'nyaman') !== false || stripos($reviewText, 'toko') !== false || stripos($reviewText, 'AC') !== false) {
                $category = 'fasilitas';
                $reason .= 'Membahas fasilitas atau kenyamanan lokasi.';
            } else {
                $reason .= 'Membahas topik umum.';
            }
        }

        $reason = "[SIMULATION] " . $reason;

        ReviewAnalysis::updateOrCreate(
            [
                'review_id' => $this->review->review_id,
                'place_id' => $this->review->place_id
            ],
            [
                'provider' => 'mocked',
                'model' => 'mock-' . config('gemini.model'),
                'prompt_version' => config('gemini.prompt_version', 'v1.0'),
                'spam_label' => $isSpam,
                'confidence' => 0.85,
                'category' => $category,
                'reason' => $reason,
                'raw_response' => json_encode(['mocked' => true, 'original_text' => $reviewText]),
                'analyzed_at' => now(),
            ]
        );
    }
}
