<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhotoAnalysis extends Model
{
    protected $fillable = [
        'photo_id', 'provider', 'model', 'prompt_version', 'label', 'category', 'confidence',
        'extracted_phones', 'official_phones', 'phone_details', 'phone_mismatch', 'flags', 'reason', 'raw_response',
        'execution_time_ms', 'input_tokens', 'output_tokens', 'cost', 'analyzed_at',
    ];

    protected $casts = [
        'extracted_phones' => 'array',
        'official_phones' => 'array',
        'phone_details' => 'array',
        'flags' => 'array',
        'phone_mismatch' => 'boolean',
        'confidence' => 'float',
        'cost' => 'float',
        'analyzed_at' => 'datetime',
    ];

    public function photo()
    {
        return $this->belongsTo(PlacePhoto::class, 'photo_id');
    }
}
