<?php

namespace App\Services;

use App\Jobs\ClassifyPhotosJob;
use App\Models\Review;
use App\Models\ScrapeJob;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Runs run_db_urls.py inside the scraper directory, so callers (artisan
 * command, queued job) never need to cd into storage/scraper themselves.
 */
class ReviewScraperRunner
{
    const MODES = ['new_only', 'update', 'full'];

    /**
     * @param  int  $limit  Stores to scrape (oldest-scraped first)
     * @param  int  $offset  Stores to skip in that ordering
     * @param  array  $kodeToko  Explicit store codes instead of the oldest ones
     * @param  string|null  $mode  new_only|update|full (null = scraper config.yaml)
     * @param  callable|null  $onOutput  Receives each chunk of scraper output
     * @return int  Process exit code (0 = success)
     */
    public function run($limit = 40, $offset = 0, array $kodeToko = [], $mode = null, $onOutput = null)
    {
        $scraperPath = config('scraper.path');

        if (!is_file($scraperPath . DIRECTORY_SEPARATOR . 'run_db_urls.py')) {
            throw new RuntimeException("run_db_urls.py not found in: {$scraperPath}. Deploy the scraper there or set SCRAPER_PATH in .env.");
        }
        if ($mode && !in_array($mode, self::MODES, true)) {
            throw new RuntimeException("Invalid mode '{$mode}'. Use new_only, update or full.");
        }

        $command = [
            config('scraper.python'),
            'run_db_urls.py',
            '--limit-stores=' . (int) $limit,
            '--offset=' . (int) $offset,
        ];
        if ($kodeToko) {
            $command[] = '--kode-toko=' . implode(',', $kodeToko);
        }
        // Everything after the script's own options is forwarded to start.py.
        $command[] = '-q';
        if ($mode) {
            array_push($command, '--scrape-mode', $mode);
        }

        if ($onOutput) {
            $onOutput('Running: ' . implode(' ', $command) . PHP_EOL);
        }

        $before = $this->reviewCounts();
        $startedAt = now();

        // The scripts print non-ASCII symbols; force UTF-8 so a Windows console
        // code page does not crash them after the work is already done.
        $process = new Process($command, $scraperPath, ['PYTHONUTF8' => '1', 'PYTHONIOENCODING' => 'utf-8'], null, config('scraper.timeout'));
        $process->run(function ($type, $buffer) use ($onOutput) {
            if ($onOutput) {
                $onOutput($buffer);
            }
        });

        $this->recordJobs($before, $startedAt, $process->isSuccessful());

        if ($process->isSuccessful()) {
            $this->afterScrape($onOutput);
        }

        return (int) $process->getExitCode();
    }

    /**
     * Copy the scraped photos into place_photos and queue their classification.
     * Failures here must not turn a successful scrape into a failed one.
     *
     * @param  callable|null  $onOutput
     * @return void
     */
    protected function afterScrape($onOutput)
    {
        try {
            $seen = app(PhotoSyncService::class)->syncAll();
            if ($onOutput) {
                $onOutput("Synced {$seen} gallery photos." . PHP_EOL);
            }
            if (config('photo_ai.enabled') && config('photo_ai.auto_after_scrape')) {
                ClassifyPhotosJob::dispatch();
            }
        } catch (\Exception $e) {
            Log::warning('[scrape] photo sync/classification step failed: ' . $e->getMessage());
        }
    }

    /**
     * Review count per place.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function reviewCounts()
    {
        return Review::selectRaw('place_id, COUNT(*) as total')
            ->groupBy('place_id')
            ->pluck('total', 'place_id');
    }

    /**
     * Write one scrape_jobs row per place whose review count grew.
     * scrape_jobs.place_id is a foreign key to places, so only places the
     * scraper has already written can be recorded.
     *
     * @param  \Illuminate\Support\Collection  $before
     * @param  \Illuminate\Support\Carbon  $startedAt
     * @param  bool  $successful
     * @return void
     */
    protected function recordJobs($before, $startedAt, $successful)
    {
        $finishedAt = now();

        foreach ($this->reviewCounts() as $placeId => $total) {
            $new = $total - ($before[$placeId] ?? 0);
            if ($new <= 0) {
                continue;
            }

            ScrapeJob::create([
                'place_id' => $placeId,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'duration' => $finishedAt->diffInSeconds($startedAt),
                'status' => $successful ? 'success' : 'partial',
                'new_reviews' => $new,
            ]);
        }
    }
}
