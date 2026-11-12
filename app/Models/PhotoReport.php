<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhotoReport extends Model
{
    const STATUSES = [
        'pending' => 'Belum dilaporkan',
        'reported' => 'Sudah dilaporkan ke Google',
        'dismissed' => 'Bukan spam (diabaikan)',
    ];

    protected $fillable = ['photo_id', 'status', 'note', 'reported_at'];

    protected $casts = [
        'reported_at' => 'datetime',
    ];

    public function photo()
    {
        return $this->belongsTo(PlacePhoto::class, 'photo_id');
    }
}
