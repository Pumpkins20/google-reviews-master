<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Place extends Model
{
    use HasFactory;

    protected $table = 'places';
    protected $primaryKey = 'place_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'place_id',
        'store_code',
        'place_name',
        'original_url',
        'resolved_url',
        'latitude',
        'longitude',
        'first_seen',
        'last_scraped',
        'total_reviews',
        'photos',
        'photos_updated_at',
    ];

    protected $casts = [
        'photos' => 'array',
    ];

    /**
     * Gallery photo URLs sized for display (stored URLs have no size suffix).
     *
     * @param  string  $size  e.g. w400-h300-k-no
     * @return array
     */
    public function photoUrls($size = 'w400-h300-k-no')
    {
        return array_map(function ($url) use ($size) {
            return $url . '=' . $size;
        }, $this->photos ?: []);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'place_id', 'place_id');
    }
}
