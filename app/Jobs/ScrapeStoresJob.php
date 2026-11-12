<?php

namespace App\Jobs;

use App\Services\ReviewScraperRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ScrapeStoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * A scrape drives a real browser; never retry it automatically.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * Seconds the worker lets the job run (set from config in the constructor).
     *
     * @var int
     */
    public $timeout;

    protected $limit;
    protected $offset;
    protected $kodeToko;
    protected $mode;

    /**
     * @param  array  $kodeToko  Store codes to scrape; empty = oldest-scraped stores
     * @param  int  $limit
     * @param  int  $offset
     * @param  string|null  $mode  new_only|update|full
     */
    public function __construct(array $kodeToko = [], $limit = 40, $offset = 0, $mode = null)
    {
        $this->kodeToko = $kodeToko;
        $this->limit = (int) $limit;
        $this->offset = (int) $offset;
        $this->mode = $mode;
        $this->timeout = (int) config('scraper.timeout') + 60;
    }

    /**
     * Only one scrape at a time: two browsers writing the same SQLite file
     * would collide. A second job waits instead of running in parallel.
     *
     * @return array
     */
    public function middleware()
    {
        return [(new WithoutOverlapping('scrape-reviews'))
            ->releaseAfter(60)
            ->expireAfter((int) config('scraper.timeout') + 120)];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ReviewScraperRunner $runner)
    {
        $exit = $runner->run($this->limit, $this->offset, $this->kodeToko, $this->mode, function ($chunk) {
            Log::info('[scrape] ' . rtrim($chunk));
        });

        if ($exit !== 0) {
            throw new RuntimeException("Scraper exited with code {$exit}. See storage/logs/laravel.log.");
        }
    }
}
