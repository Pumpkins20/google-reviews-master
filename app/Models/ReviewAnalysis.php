<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewAnalysis extends Model
{
    use HasFactory;

    protected $table = 'review_analysis';

    protected $fillable = [
        'review_id',
        'place_id',
        'provider',
        'model',
        'prompt_version',
        'spam_label',
        'confidence',
        'category',
        'reason',
        'raw_response',
        'execution_time_ms',
        'input_tokens',
        'output_tokens',
        'cost',
        'analyzed_at',
    ];

    protected $dates = [
        'analyzed_at',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class, 'review_id', 'review_id');
    }
}
