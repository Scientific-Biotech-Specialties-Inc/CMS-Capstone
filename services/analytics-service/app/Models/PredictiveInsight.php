<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PredictiveInsight extends Model
{
    protected $fillable = [
        'metric_type',
        'forecast_date',
        'forecast_horizon_days',
        'historical_series',
        'predicted_series',
        'confidence',
        'ai_narrative',
        'generated_at',
    ];

    protected $casts = [
        'forecast_date'     => 'date',
        'historical_series' => 'array',
        'predicted_series'  => 'array',
        'generated_at'      => 'datetime',
    ];
}
