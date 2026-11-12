<?php

namespace App\Console\Commands;

use App\Jobs\ClassifyPhotosJob;
use App\Models\Place;
use App\Models\PlacePhoto;
use App\Services\Ai\PhotoClassifier;
use App\Services\PhotoSyncService;
use Illuminate\Console\Command;

class ClassifyPhotos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grms:classify-photos
                            {--kode-toko= : Comma-separated store codes (default: all stores)}
                            {--limit= : Max photos this run (default: PHOTO_AI_MAX_PER_RUN)}
                            {--force : Re-analyse photos that already have a result}
                            {--stale : Re-analyse only results made with an older prompt version}
                            {--recompute : Re-apply the current verdict rules to stored results (no Gemini calls, no cost)}
                            {--sync-only : Only copy scraped photos into place_photos, no AI calls}
                            {--queue : Dispatch as a queued job}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Classify scraped store photos with Gemini (scam / suspicious contact details) and record the cost';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(PhotoSyncService $sync, PhotoClassifier $classifier)
    {
        $count = $sync->syncAll();
        $this->info("Synced photos of {$count} gallery entries into place_photos.");

        if ($this->option('sync-only')) {
            return 0;
        }

        $codes = array_filter(array_map('trim', explode(',', (string) $this->option('kode-toko'))));
        $placeIds = $codes ? Place::whereIn('store_code', $codes)->pluck('place_id')->all() : [];
        if ($codes && !$placeIds) {
            $this->error('No place found for the given store codes.');
            return 1;
        }
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if ($this->option('recompute')) {
            return $this->recompute($classifier, $placeIds);
        }

        if ($this->option('queue')) {
            ClassifyPhotosJob::dispatch($placeIds, $limit, (bool) $this->option('force'), (bool) $this->option('stale'));
            $this->info('Photo classification job dispatched.');
            return 0;
        }

        $photos = PlacePhoto::active()
            ->when($placeIds, function ($q) use ($placeIds) {
                $q->whereIn('place_id', $placeIds);
            })
            ->when($this->option('stale'), function ($q) {
                $q->stale();
            })
            ->when(!$this->option('force') && !$this->option('stale'), function ($q) {
                $q->whereNull('analyzed_at');
            })
            ->orderBy('id')
            ->limit($limit ?: (int) config('photo_ai.max_per_run'))
            ->get();

        $this->info("Classifying {$photos->count()} photos with " . config('photo_ai.model') . '...');
        $cost = 0;
        $labels = [];
        foreach ($photos as $photo) {
            try {
                $analysis = $classifier->classify($photo);
                $cost += (float) $analysis->cost;
                $labels[$analysis->label] = ($labels[$analysis->label] ?? 0) + 1;
                $this->line("#{$photo->id} {$analysis->label} (" . (int) $analysis->input_tokens . '+' . (int) $analysis->output_tokens . ' tokens, $' . number_format((float) $analysis->cost, 6) . ')');
            } catch (\Exception $e) {
                $this->warn("#{$photo->id} failed: " . $e->getMessage());
                if (strpos($e->getMessage(), '429') !== false) {
                    $this->warn('Rate limited; stopping.');
                    break;
                }
            }
        }

        $this->info('Done. ' . json_encode($labels) . ' total cost $' . number_format($cost, 6));

        return 0;
    }

    /**
     * Apply the current verdict rules to the stored result of each photo.
     * Only results that kept their per-number details (prompt v2) can be redone;
     * older ones need a new analysis (--stale).
     *
     * @param  \App\Services\Ai\PhotoClassifier  $classifier
     * @param  array  $placeIds
     * @return int
     */
    protected function recompute(PhotoClassifier $classifier, array $placeIds)
    {
        $photos = PlacePhoto::active()
            ->when($placeIds, function ($q) use ($placeIds) {
                $q->whereIn('place_id', $placeIds);
            })
            ->whereNotNull('analyzed_at')
            ->with('latestAnalysis')
            ->orderBy('id')
            ->get();

        $changed = [];
        $skipped = 0;
        $total = 0;
        foreach ($photos as $photo) {
            $analysis = $photo->latestAnalysis;
            if (!$analysis) {
                continue;
            }
            $before = $analysis->label;
            if (!$classifier->recompute($analysis)) {
                $skipped++;
                continue;
            }
            $total++;
            $after = $analysis->fresh()->label;
            if ($before !== $after) {
                $changed[] = "#{$photo->id} {$before} -> {$after}";
            }
        }

        $this->info("Recomputed {$total} results, {$skipped} skipped (older prompt, run with --stale), " . count($changed) . ' label changes.');
        foreach ($changed as $line) {
            $this->line($line);
        }

        return 0;
    }
}
