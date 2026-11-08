<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Reused for both Feature 1 (RAG risk-assessment: 'playbook_clause',
 * 'contract_chunk' entity types) and Feature 3 (Vendor AI Suggestions:
 * 'vendor', 'vendor_suggestion_candidate' entity types). `entity_type` is
 * a plain discriminator column — no schema change was needed to add new
 * entity types beyond what the original candidate schema already provided.
 */
class Embedding extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'embedding',
        'model_name',
    ];
}
