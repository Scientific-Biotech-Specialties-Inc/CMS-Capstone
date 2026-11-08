<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AggregatedMetric extends Model
{
    protected $fillable = [
        'metric_type',
        'source_service',
        'source_record_id',
        'metric_value',
        'metric_date',
        'metadata',
    ];

    protected $casts = [
        'metadata'    => 'array',
        'metric_date' => 'date',
    ];
}
