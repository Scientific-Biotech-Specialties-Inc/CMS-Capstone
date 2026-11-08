<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskAssessmentFinding extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'risk_assessment_result_id',
        'clause_reference',
        'severity',
        'playbook_clause_id',
        'retrieval_score',
        'deviation_reason',
        'recommended_remediation',
        'created_at',
    ];

    protected $casts = [
        'created_at'      => 'datetime',
        'retrieval_score' => 'float',
    ];

    public function result(): BelongsTo
    {
        return $this->belongsTo(RiskAssessmentResult::class, 'risk_assessment_result_id');
    }

    public function playbookClause(): BelongsTo
    {
        return $this->belongsTo(PlaybookClause::class, 'playbook_clause_id');
    }
}
