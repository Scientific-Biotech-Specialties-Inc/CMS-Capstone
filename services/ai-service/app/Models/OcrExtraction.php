<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OcrExtraction extends Model
{
    protected $fillable = [
        'document_id',
        'contract_id',
        'extracted_fields',
        'confidence_score',
        'status',
        'extracted_at',
    ];

    protected $casts = [
        'extracted_fields' => 'array',
        'extracted_at' => 'datetime',
        'confidence_score' => 'float',
    ];
}
