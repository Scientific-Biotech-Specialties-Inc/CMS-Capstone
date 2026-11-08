<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSuggestionCandidate extends Model
{
    protected $fillable = [
        'vendor_suggestion_id',
        'candidate_name',
        'candidate_industry',
        'candidate_region',
        'candidate_contact_email',
        'candidate_contact_number',
        'candidate_address',
        'suggestion_score',
        'suggestion_reason',
        'gemini_raw_response',
        'decision',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'gemini_raw_response' => 'array',
        'decided_at'          => 'datetime',
    ];

    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(VendorSuggestion::class, 'vendor_suggestion_id');
    }
}
