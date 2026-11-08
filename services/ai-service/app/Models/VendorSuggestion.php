<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorSuggestion extends Model
{
    protected $fillable = [
        'vendor_type',
        'vendor_id',
        'contract_id',
        'requested_by',
        'industry_hint',
        'region_hint',
        'suggestion_score',
        'suggestion_reason',
        'status',
        'suggested_at',
    ];

    protected $casts = [
        'suggested_at' => 'datetime',
    ];

    public function candidates(): HasMany
    {
        return $this->hasMany(VendorSuggestionCandidate::class, 'vendor_suggestion_id');
    }
}
