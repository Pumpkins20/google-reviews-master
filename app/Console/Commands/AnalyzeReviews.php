<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Review;
use App\Jobs\AnalyzeReviewsBatchJob;

class AnalyzeReviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grms:analyze-reviews 
                            {--limit=100 : The number of reviews to analyze} 
                            {--provider= : The AI provider to use (gemini, openai, groq, mock)} 
                            {--batch-size=10 : The size of each batch sent to the provider}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue reviews for batched AI classification analysis';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $batchSize = (int) $this->option('batch-size');
        $provider = $this->option('provider') ?: config('ai.default', 'gemini');

        $this->info("AI Provider: {$provider}");
        $this->info("Batch Size: {$batchSize}");
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
            $this->info("Found {$emptyReviewsCount} unanalyzed empty/rating-only reviews. Marking them as system-analyzed...");
            foreach ($emptyReviews as $review) {
                \App\Models\ReviewAnalysis::create([
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
            $this->info("Successfully pre-processed {$emptyReviewsCount} empty reviews.");
        }

        $this->info("Looking for unanalyzed non-empty reviews for provider '{$provider}' (limit: {$limit})...");

        // 2. Find only non-empty reviews that do not have an analysis for this provider
        $reviews = Review::whereNotNull('review_text')
            ->where('review_text', '!=', '')
            ->where('review_text', '!=', '[]')
            ->where('review_text', '!=', '{}')
            ->whereDoesntHave('analyses', function ($q) use ($provider) {
                $q->where('provider', $provider)
                  ->orWhere('provider', 'system');
            })->limit($limit)->get();

        $count = $reviews->count();

        if ($count === 0) {
            $this->info("All reviews have already been analyzed by provider '{$provider}'!");
            return 0;
        }

        $this->info("Queueing {$count} reviews in batches of {$batchSize} for AI analysis...");

        $chunks = $reviews->chunk($batchSize);
        $bar = $this->output->createProgressBar($chunks->count());
        $bar->start();

        foreach ($chunks as $chunk) {
            $reviewIds = $chunk->pluck('review_id')->toArray();
            AnalyzeReviewsBatchJob::dispatch($reviewIds, $provider);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully queued {$chunks->count()} batches. Run 'php artisan queue:work' to process them.");

        return 0;
    }
}
