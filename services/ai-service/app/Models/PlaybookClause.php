<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaybookClause extends Model
{
    protected $fillable = [
        'clause_code',
        'title',
        'standard_text',
        'category',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function findings(): HasMany
    {
        return $this->hasMany(RiskAssessmentFinding::class, 'playbook_clause_id');
    }
}
