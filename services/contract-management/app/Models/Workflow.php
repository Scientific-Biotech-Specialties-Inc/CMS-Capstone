<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The approval-chain template for one contract type. Only a workflow with
 * status = 'active' is used for routing; 'draft' workflows are editable
 * scratch space that never affects live contracts (decision #11).
 */
class Workflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_type_id',
        'name',
        'status',
        'resubmit_mode',
        'created_by',
        'execution_reviewers',
    ];

    protected $casts = ['execution_reviewers' => 'array'];

    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractCategory::class, 'contract_type_id', 'category_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class, 'workflow_id')->orderBy('order_index');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ApprovalInstance::class, 'workflow_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
