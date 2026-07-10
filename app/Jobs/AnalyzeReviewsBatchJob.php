<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Review;
use App\Models\ReviewAnalysis;
use App\Services\Ai\AiManager;
use Illuminate\Support\Facades\Log;
use Exception;

class AnalyzeReviewsBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 30;

    protected $reviewIds;
    protected $provider;
    protected $model;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $reviewIds, $provider = null, $model = null)
    {
        $this->reviewIds = $reviewIds;
        $this->provider = $provider;
        $this->model = $model;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(AiManager $aiManager)
    {
        $reviews = Review::whereIn('review_id', $this->reviewIds)->get();
        if ($reviews->isEmpty()) {
            return;
        }

        $providerName = $this->provider ?: config('ai.default', 'gemini');
        $driver = $aiManager->provider($providerName);

        $emptyReviews = [];
        $validReviews = [];

        foreach ($reviews as $review) {
            $text = $this->getReviewText($review);
            if (empty($text)) {
                $emptyReviews[] = $review;
            } else {
                $validReviews[] = [
                    'review_id' => $review->review_id,
                    'rating' => $review->rating,
                    'review_text' => $text,
                ];
            }
        }

        // 1. Process empty reviews immediately as system classification
        foreach ($emptyReviews as $review) {
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
        }

        if (empty($validReviews)) {
            return;
        }

        // 2. Process non-empty reviews via AI provider
        try {
            $results = $driver->analyzeBatch($validReviews);
            $modelName = $this->model ?: (config("ai.providers.{$providerName}.model") ?? 'default');

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
                        'provider' => $providerName,
                        'model' => $modelName,
                    ],
                    [
                        'prompt_version' => config('ai.prompt_version', 'v2.0-batched'),
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
            }
        } catch (Exception $e) {
            Log::error("Failed in AnalyzeReviewsBatchJob for provider {$providerName}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract the review text.
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
