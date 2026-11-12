<?php

namespace App\Jobs;

use App\Models\PlacePhoto;
use App\Services\Ai\PhotoClassifier;
use App\Services\PhotoSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ClassifyPhotosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 3000;

    protected $placeIds;
    protected $limit;
    protected $force;
    protected $stale;

    /**
     * @param  array  $placeIds  Limit to these places; empty = every place
     * @param  int|null  $limit  Max photos this run (default: PHOTO_AI_MAX_PER_RUN)
     * @param  bool  $force  Re-analyse photos that already have a result
     * @param  bool  $stale  Re-analyse only results made with an older prompt version
     */
    public function __construct(array $placeIds = [], $limit = null, $force = false, $stale = false)
    {
        $this->stale = $stale;
        $this->placeIds = $placeIds;
        $this->limit = $limit;
        $this->force = $force;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(PhotoClassifier $classifier, PhotoSyncService $sync)
    {
        if (!config('photo_ai.enabled')) {
            return;
        }

        $sync->syncAll();

        $limit = (int) ($this->limit ?: config('photo_ai.max_per_run'));
        $query = PlacePhoto::active()
            ->when($this->placeIds, function ($q) {
                $q->whereIn('place_id', $this->placeIds);
            })
            ->when($this->stale, function ($q) {
                $q->stale();
            })
            ->when(!$this->force && !$this->stale, function ($q) {
                $q->whereNull('analyzed_at');
            })
            ->orderBy('id')
            ->limit($limit);

        $done = 0;
        $failed = 0;
        foreach ($query->get() as $photo) {
            try {
                $classifier->classify($photo);
                $done++;
            } catch (\Exception $e) {
                $failed++;
                Log::warning("[photo-ai] photo #{$photo->id}: " . $e->getMessage());
                if (strpos($e->getMessage(), '429') !== false) {
                    Log::warning('[photo-ai] rate limited, stopping this run; the rest is picked up next run.');
                    break;
                }
            }
        }

        Log::info("[photo-ai] classified {$done} photos, {$failed} failed.");
    }
}
