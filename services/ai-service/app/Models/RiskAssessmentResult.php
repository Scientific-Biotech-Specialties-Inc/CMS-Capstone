<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskAssessmentResult extends Model
{
    protected $fillable = [
        'document_id',
        'contract_id',
        'risk_score',
        'risk_level',
        'findings',
        'status',
        'scanned_at',
    ];

    protected $casts = [
        'findings'   => 'array',
        'scanned_at' => 'datetime',
    ];

    public function findingRows(): HasMany
    {
        return $this->hasMany(RiskAssessmentFinding::class, 'risk_assessment_result_id');
    }
}
