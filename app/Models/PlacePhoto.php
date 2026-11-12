<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlacePhoto extends Model
{
    const FLAGGED = ['scam', 'suspicious'];

    protected $fillable = [
        'place_id', 'url_hash', 'url', 'maps_url', 'label', 'analyzed_at',
        'first_seen_at', 'last_seen_at', 'removed_at',
    ];

    protected $casts = [
        'analyzed_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function place()
    {
        return $this->belongsTo(Place::class, 'place_id', 'place_id');
    }

    public function analyses()
    {
        return $this->hasMany(PhotoAnalysis::class, 'photo_id')->orderByDesc('id');
    }

    public function latestAnalysis()
    {
        return $this->hasOne(PhotoAnalysis::class, 'photo_id')->latestOfMany();
    }

    public function report()
    {
        return $this->hasOne(PhotoReport::class, 'photo_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('removed_at');
    }

    /**
     * Photos whose latest analysis was made with an older prompt version.
     * (Unanalysed photos are not stale; they are simply not analysed yet.)
     */
    public function scopeStale($query)
    {
        return $query->whereRaw(
            '(select prompt_version from photo_analyses where photo_id = place_photos.id order by id desc limit 1) <> ?',
            [config('photo_ai.prompt_version')]
        );
    }

    public function scopeFlagged($query)
    {
        return $query->whereIn('label', self::FLAGGED);
    }

    public function isFlagged()
    {
        return in_array($this->label, self::FLAGGED, true);
    }

    /**
     * Photo id and type from the Maps photo page link (".../!1s<id>!2e<type>...").
     *
     * @return array|null  [id, type]
     */
    public function googlePhotoRef()
    {
        if ($this->maps_url && preg_match('/!1s([A-Za-z0-9_-]+)!2e(\d+)/', $this->maps_url, $m)) {
            return [$m[1], $m[2]];
        }

        return null;
    }

    /**
     * Google's own report page for this photo (needs a signed-in Google account).
     * Null when the photo page link or the full place id (0x..:0x..) is missing.
     *
     * @return string|null
     */
    public function reportUrl()
    {
        $ref = $this->googlePhotoRef();
        if (!$ref || !preg_match('/^0x[0-9a-f]+:0x[0-9a-f]+$/i', (string) $this->place_id)) {
            return null;
        }

        return 'https://www.google.com/local/content/rap/report?postId=!1e' . $ref[1] . '!2s' . $ref[0]
            . '&t=27&cb_client=maps_sv.tactile&hl=id&gl=id&fid=' . rawurlencode($this->place_id);
    }

    /**
     * Image URL at a given width (stored URLs carry no size suffix).
     *
     * @param  string  $size  e.g. w400-h300-k-no
     * @return string
     */
    public function imageUrl($size = 'w400-h300-k-no')
    {
        return $this->url . '=' . $size;
    }
}
