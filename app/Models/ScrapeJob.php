<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapeJob extends Model
{
    protected $table = 'scrape_jobs';

    protected $fillable = [
        'place_id',
        'started_at',
        'finished_at',
        'duration',
        'status',
        'new_reviews',
        'updated_reviews',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function place()
    {
        return $this->belongsTo(Place::class, 'place_id', 'place_id');
    }

}
