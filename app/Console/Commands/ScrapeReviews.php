<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeStoresJob;
use App\Services\ReviewScraperRunner;
use Illuminate\Console\Command;
use RuntimeException;

class ScrapeReviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grms:scrape-reviews
                            {--limit-stores=40 : Number of stores to scrape (oldest-scraped first)}
                            {--offset=0 : Skip this many stores in the ordered list}
                            {--kode-toko= : Comma-separated store codes to scrape instead of the oldest ones}
                            {--mode= : Scrape mode: new_only, update or full (default: scraper config.yaml)}
                            {--queue : Dispatch as a queued job instead of running in this terminal}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run run_db_urls.py: scrape stores listed in the store table; the scraper writes straight into places/reviews';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(ReviewScraperRunner $runner)
    {
        $kodeToko = array_filter(array_map('trim', explode(',', (string) $this->option('kode-toko'))));
        $limit = (int) $this->option('limit-stores');
        $offset = (int) $this->option('offset');
        $mode = $this->option('mode') ?: null;

        if ($mode && !in_array($mode, ReviewScraperRunner::MODES, true)) {
            $this->error("Invalid --mode '{$mode}'. Use new_only, update or full.");
            return 1;
        }

        if ($this->option('queue')) {
            if (config('queue.default') === 'sync') {
                $this->warn('QUEUE_CONNECTION=sync: the job runs right here and blocks. Use QUEUE_CONNECTION=database plus "php artisan queue:work" to run it in the background.');
            }
            ScrapeStoresJob::dispatch($kodeToko, $limit, $offset, $mode);
            $this->info('Scrape job dispatched.');
            return 0;
        }

        try {
            $exit = $runner->run($limit, $offset, $kodeToko, $mode, function ($chunk) {
                $this->getOutput()->write($chunk);
            });
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        if ($exit !== 0) {
            $this->error("Scraper failed (exit code {$exit}).");
            return 1;
        }

        $this->info('Scrape complete.');
        return 0;
    }
}
