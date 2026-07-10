<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $table = 'reviews';
    protected $primaryKey = 'review_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'review_id',
        'place_id',
        'author',
        'rating',
        'review_text',
        'review_date',
        'raw_date',
        'likes',
        'user_images',
        's3_images',
        'profile_url',
        'profile_picture',
        's3_profile_picture',
        'owner_responses',
        'created_date',
        'last_modified',
        'last_seen_session',
        'last_changed_session',
        'is_deleted',
        'content_hash',
        'engagement_hash',
        'row_version',
        'sub_ratings',
    ];

    public function place()
    {
        return $this->belongsTo(Place::class, 'place_id', 'place_id');
    }

    public function analysis()
    {
        return $this->hasOne(ReviewAnalysis::class, 'review_id', 'review_id')
            ->where('provider', config('ai.default', 'gemini'));
    }

    public function analyses()
    {
        return $this->hasMany(ReviewAnalysis::class, 'review_id', 'review_id');
    }
}
