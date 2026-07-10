<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiProviderInterface;

class MockProvider implements AiProviderInterface
{
    protected $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function analyzeBatch(array $reviews): array
    {
        $startTime = microtime(true);

        // Simulate network latency (200ms - 500ms total for the batch)
        usleep(rand(200, 500) * 1000);

        $results = [];
        $totalReviews = count($reviews);

        foreach ($reviews as $review) {
            $reviewId = $review['review_id'] ?? '';
            $rating = $review['rating'] ?? 5;
            $text = mb_strtolower($review['review_text'] ?? '');

            $category = 'POSITIF';
            $reason = 'Ulasan bernada positif dan mengekspresikan kepuasan.';

            if (empty($text) || strlen($text) < 10) {
                $category = 'SPAM_PROMOSI';
                $reason = 'Ulasan terlalu pendek atau tidak bermakna (indikasi bot/spam).';
            } elseif (preg_match('/(transfer|rekening|dp|blokir|ditipu|penipu|wa admin|08\d{2})/i', $text)) {
                $category = 'INDIKASI_PENIPUAN';
                $reason = 'Terdeteksi indikasi modus penipuan transaksi keuangan atau pengalihan transaksi ke kontak luar.';
            } elseif (preg_match('/(promo|diskon|jual|cek ig|toko sebelah|murah|ig @)/i', $text) && preg_match('/(cek|beli|toko)/i', $text)) {
                $category = 'SPAM_PROMOSI';
                $reason = 'Ulasan terindikasi mengandung unsur promosi bisnis luar atau pengalihan ke akun media sosial lain.';
            } elseif (preg_match('/(tanya|stok|ada\?|ready\?|harga|berapa|jam buka)/i', $text)) {
                $category = 'PERTANYAAN';
                $reason = 'Teks bukan berupa ulasan pengalaman belanja melainkan pertanyaan operasional atau ketersediaan stok.';
            } elseif (preg_match('/(tapi|namun|tetapi|meskipun|walaupun)/i', $text) || ($rating >= 3 && $rating <= 4 && preg_match('/(kurang|lambat|sempit|susah|tapi)/i', $text))) {
                $category = 'CAMPURAN';
                $reason = 'Ulasan mengekspresikan kepuasan pada aspek tertentu tetapi keluhan pada aspek lainnya.';
            } elseif ($rating <= 2 || preg_match('/(kecewa|buruk|jelek|slowrespon|lambat|kapok)/i', $text)) {
                $category = 'NEGATIF';
                $reason = 'Ulasan mengekspresikan ketidakpuasan murni terhadap produk, pelayanan, atau fasilitas toko.';
            }

            // Determine spam_label
            $spamLabel = in_array($category, ['SPAM_PROMOSI', 'INDIKASI_PENIPUAN']) ? 'spam' : 'ham';

            $results[] = [
                'review_id' => $reviewId,
                'category' => $category,
                'spam_label' => $spamLabel,
                'confidence' => round(rand(80, 99) / 100, 2),
                'reason' => '[MOCK] ' . $reason,
                'raw_response' => json_encode([
                    'mocked' => true,
                    'review_id' => $reviewId,
                    'category' => $category,
                ]),
            ];
        }

        $endTime = microtime(true);
        $totalDurationMs = ($endTime - $startTime) * 1000;

        // Distribute execution time, token usage and cost equally
        $apportionedTime = $totalDurationMs / max(1, $totalReviews);

        return array_map(function ($item) use ($apportionedTime) {
            $item['execution_time_ms'] = round($apportionedTime, 2);
            $item['input_tokens'] = 0;
            $item['output_tokens'] = 0;
            $item['cost'] = 0.000000;
            return $item;
        }, $results);
    }
}
