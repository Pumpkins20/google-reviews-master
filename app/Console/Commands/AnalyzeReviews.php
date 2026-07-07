<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Review;
use App\Jobs\AnalyzeReviewJob;

class AnalyzeReviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grms:analyze-reviews {--limit=100 : The number of reviews to queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue reviews for Gemini AI classification analysis';

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

        $this->info("Looking for unanalyzed reviews (limit: {$limit})...");

        // Find reviews that do not have an entry in the review_analysis table
        $reviews = Review::whereDoesntHave('analysis')
            ->limit($limit)
            ->get();

        $count = $reviews->count();

        if ($count === 0) {
            $this->info("All reviews have already been analyzed!");
            return 0;
        }

        $this->info("Queueing {$count} reviews for AI analysis...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($reviews as $review) {
            AnalyzeReviewJob::dispatch($review);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully queued {$count} jobs. Run 'php artisan queue:work' to process them.");

        return 0;
    }
}
