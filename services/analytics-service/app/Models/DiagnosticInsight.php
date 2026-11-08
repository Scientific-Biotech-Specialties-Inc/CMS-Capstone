<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiagnosticInsight extends Model
{
    protected $fillable = [
        'metric_type',
        'period_start',
        'period_end',
        'finding_summary',
        'ai_narrative',
        'generated_at',
    ];

    protected $casts = [
        'period_start'    => 'date',
        'period_end'      => 'date',
        'finding_summary' => 'array',
        'generated_at'    => 'datetime',
    ];
}
