<?php

namespace App\Services;

use App\Models\Place;
use App\Models\PlacePhoto;

/**
 * Turns places.photos (JSON written by the scraper) into place_photos rows,
 * one per photo, so each photo can be analysed and reported on its own.
 */
class PhotoSyncService
{
    /**
     * @return int  Number of photos seen across all places
     */
    public function syncAll()
    {
        $count = 0;

        Place::whereNotNull('photos')->get()->each(function (Place $place) use (&$count) {
            $count += $this->syncPlace($place);
        });

        return $count;
    }

    /**
     * @param  \App\Models\Place  $place
     * @return int  Number of photos in the place's current gallery
     */
    public function syncPlace(Place $place)
    {
        $entries = $this->normalize($place->photos ?: []);
        if (!$entries) {
            return 0;
        }

        $now = now();
        $seenHashes = [];

        foreach ($entries as $entry) {
            $hash = sha1($entry['url']);
            $seenHashes[] = $hash;

            $photo = PlacePhoto::firstOrNew(['place_id' => $place->place_id, 'url_hash' => $hash]);
            if (!$photo->exists) {
                $photo->url = $entry['url'];
                $photo->first_seen_at = $now;
            }
            // Never replace a known page link with a missing one.
            if ($entry['maps_url']) {
                $photo->maps_url = $entry['maps_url'];
            }
            $photo->last_seen_at = $now;
            $photo->removed_at = null;
            $photo->save();
        }

        // Photos that disappeared from the gallery (deleted or reported) stay as history.
        PlacePhoto::where('place_id', $place->place_id)
            ->whereNotIn('url_hash', $seenHashes)
            ->whereNull('removed_at')
            ->update(['removed_at' => $now]);

        return count($entries);
    }

    /**
     * Older rows hold bare URLs; newer ones hold {url, maps_url}.
     *
     * @param  array  $photos
     * @return array
     */
    protected function normalize(array $photos)
    {
        $entries = [];

        foreach ($photos as $photo) {
            $url = is_array($photo) ? ($photo['url'] ?? null) : $photo;
            if (!$url) {
                continue;
            }
            $entries[] = [
                'url' => $url,
                'maps_url' => is_array($photo) ? ($photo['maps_url'] ?? null) : null,
            ];
        }

        return $entries;
    }
}
