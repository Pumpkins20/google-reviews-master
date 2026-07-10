<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Review;
use App\Models\ReviewAnalysis;
use App\Services\Ai\AiManager;
use Illuminate\Support\Facades\DB;
use Exception;

class AiComparisonController extends Controller
{
    /**
     * Display a comparison dashboard of AI providers.
     */
    public function index()
    {
        // Get metrics grouped by provider and model
        $metrics = ReviewAnalysis::select(
                'provider',
                'model',
                DB::raw('COUNT(*) as total_reviews'),
                DB::raw('AVG(execution_time_ms) as avg_latency'),
                DB::raw('SUM(input_tokens) as total_input_tokens'),
                DB::raw('SUM(output_tokens) as total_output_tokens'),
                DB::raw('SUM(cost) as total_cost'),
                DB::raw('AVG(confidence) as avg_confidence'),
                DB::raw("SUM(CASE WHEN spam_label = 'spam' THEN 1 ELSE 0 END) as spam_count"),
                DB::raw("SUM(CASE WHEN spam_label = 'ham' THEN 1 ELSE 0 END) as ham_count")
            )
            ->groupBy('provider', 'model')
            ->get();

        $totalReviewsCount = Review::count();

        // Calculate unanalyzed reviews for each provider
        $providers = [
            'gemini' => 'Gemini 2.5 Flash Lite',
            'groq' => 'Groq Llama 3.1 8B',
        ];

        $unanalyzedCounts = [];
        foreach ($providers as $key => $name) {
            $analyzedCount = ReviewAnalysis::where('provider', $key)
                ->orWhere('provider', 'system')
                ->count();
            $unanalyzedCounts[$key] = max(0, $totalReviewsCount - $analyzedCount);
        }

        // Global stats
        $totalCostOverall = ReviewAnalysis::sum('cost') ?? 0;
        $totalAnalyzedOverall = ReviewAnalysis::count();
        $avgLatencyOverall = ReviewAnalysis::avg('execution_time_ms') ?? 0;
        $avgConfidenceOverall = ReviewAnalysis::avg('confidence') ?? 0;

        return view('dashboard.comparison', compact(
            'metrics',
            'unanalyzedCounts',
            'totalReviewsCount',
            'totalCostOverall',
            'totalAnalyzedOverall',
            'avgLatencyOverall',
            'avgConfidenceOverall',
            'providers'
        ));
    }

    /**
     * Run batch analysis manually for a provider.
     */
    public function analyze(Request $request, AiManager $aiManager)
    {
        $request->validate([
            'provider' => 'required|in:gemini,openai,groq,mock',
            'limit' => 'required|integer|min:1|max:100',
            'batch_size' => 'required|integer|min:1|max:50',
        ]);

        $provider = $request->input('provider');
        $limit = (int) $request->input('limit');
        $batchSize = (int) $request->input('batch_size');

        // Check api key configured (except for mock)
        if ($provider !== 'mock' && empty(config("ai.providers.{$provider}.api_key"))) {
            return redirect()->back()->with('error', "API Key untuk provider '{$provider}' belum dikonfigurasi di file .env!");
        }

        // 1. Instantly classify all unanalyzed empty/rating-only reviews locally as system
        $emptyReviews = Review::where(function ($q) {
            $q->whereNull('review_text')
              ->orWhere('review_text', '')
              ->orWhere('review_text', '[]')
              ->orWhere('review_text', '{}');
        })->whereDoesntHave('analyses', function ($q) {
            $q->where('provider', 'system');
        })->get(['review_id', 'place_id']);

        $emptyReviewsCount = $emptyReviews->count();

        if ($emptyReviewsCount > 0) {
            foreach ($emptyReviews as $review) {
                ReviewAnalysis::create([
                    'review_id' => $review->review_id,
                    'place_id' => $review->place_id,
                    'provider' => 'system',
                    'model' => 'empty-text-detector',
                    'prompt_version' => config('ai.prompt_version', 'v2.0-batched'),
                    'spam_label' => 'ham',
                    'confidence' => 1.0,
                    'category' => 'PERTANYAAN',
                    'reason' => 'Ulasan hanya berupa rating bintang (tanpa teks)',
                    'raw_response' => json_encode(['system_skipped' => true]),
                    'execution_time_ms' => 0,
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'cost' => 0.00,
                    'analyzed_at' => now(),
                ]);
            }
        }

        // 2. Get only non-empty unanalyzed reviews for this provider
        $reviews = Review::whereNotNull('review_text')
            ->where('review_text', '!=', '')
            ->where('review_text', '!=', '[]')
            ->where('review_text', '!=', '{}')
            ->whereDoesntHave('analyses', function ($q) use ($provider) {
                $q->where('provider', $provider)
                  ->orWhere('provider', 'system');
            })->limit($limit)->get();

        if ($reviews->isEmpty()) {
            if ($emptyReviewsCount > 0) {
                return redirect()->back()->with('success', "Berhasil memproses {$emptyReviewsCount} ulasan kosong secara lokal!");
            }
            return redirect()->back()->with('info', "Semua ulasan sudah dianalisis oleh provider '{$provider}'!");
        }

        $driver = $aiManager->provider($provider);

        $emptyCount = 0;
        $validReviews = [];
        $startTime = microtime(true);

        foreach ($reviews as $review) {
            $text = $this->getReviewText($review);
            if (empty($text)) {
                $emptyCount++;
                ReviewAnalysis::updateOrCreate(
                    [
                        'review_id' => $review->review_id,
                        'place_id' => $review->place_id,
                        'provider' => 'system',
                        'model' => 'empty-text-detector',
                    ],
                    [
                        'prompt_version' => config('ai.prompt_version', 'v2.0-batched'),
                        'spam_label' => 'ham',
                        'confidence' => 1.0,
                        'category' => 'PERTANYAAN',
                        'reason' => 'Ulasan hanya berupa rating bintang (tanpa teks)',
                        'raw_response' => json_encode(['system_skipped' => true]),
                        'execution_time_ms' => 0,
                        'input_tokens' => 0,
                        'output_tokens' => 0,
                        'cost' => 0.00,
                        'analyzed_at' => now(),
                    ]
                );
            } else {
                $validReviews[] = [
                    'review_id' => $review->review_id,
                    'rating' => $review->rating,
                    'review_text' => $text,
                ];
            }
        }

        $analyzedCount = 0;
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $totalCost = 0;

        if (!empty($validReviews)) {
            try {
                $chunks = array_chunk($validReviews, $batchSize);
                $modelName = config("ai.providers.{$provider}.model") ?? 'default';

                foreach ($chunks as $chunk) {
                    $results = $driver->analyzeBatch($chunk);

                    foreach ($results as $res) {
                        $reviewId = $res['review_id'];
                        $original = $reviews->firstWhere('review_id', $reviewId);
                        if (!$original) {
                            continue;
                        }

                        ReviewAnalysis::updateOrCreate(
                            [
                                'review_id' => $reviewId,
                                'place_id' => $original->place_id,
                                'provider' => $provider,
                                'model' => $modelName,
                            ],
                            [
                                'prompt_version' => config('ai.prompt_version'),
                                'spam_label' => $res['spam_label'],
                                'confidence' => $res['confidence'],
                                'category' => $res['category'],
                                'reason' => $res['reason'],
                                'raw_response' => $res['raw_response'],
                                'execution_time_ms' => $res['execution_time_ms'] ?? null,
                                'input_tokens' => $res['input_tokens'] ?? null,
                                'output_tokens' => $res['output_tokens'] ?? null,
                                'cost' => $res['cost'] ?? null,
                                'analyzed_at' => now(),
                            ]
                        );

                        $analyzedCount++;
                        $totalInputTokens += $res['input_tokens'] ?? 0;
                        $totalOutputTokens += $res['output_tokens'] ?? 0;
                        $totalCost += $res['cost'] ?? 0;
                    }
                }
            } catch (Exception $e) {
                return redirect()->back()->with('error', "Gagal menjalankan analisis: " . $e->getMessage());
            }
        }

        $duration = round(microtime(true) - $startTime, 2);
        $totalEmptyProcessed = $emptyCount + $emptyReviewsCount;

        return redirect()->route('dashboard.comparison')
            ->with('success', "Analisis batch selesai! Memproses {$analyzedCount} ulasan teks & {$totalEmptyProcessed} ulasan kosong dalam {$duration}s menggunakan {$provider}. Total biaya: \$" . number_format($totalCost, 6) . ".");
    }

    /**
     * Extract clean review text.
     */
    protected function getReviewText($review)
    {
        $raw = $review->review_text;
        if (empty($raw)) {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            if (!empty($decoded['id'])) {
                return $decoded['id'];
            }
            if (!empty($decoded['en'])) {
                return $decoded['en'];
            }
            foreach ($decoded as $val) {
                if (!empty($val)) {
                    return $val;
                }
            }
            return '';
        }

        return $raw;
    }
}
