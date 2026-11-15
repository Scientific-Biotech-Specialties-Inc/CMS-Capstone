<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One contract's run through a workflow. run_number increments on
 * resubmission after a rejection (spec 5.5) — the prior run's rejection
 * stays in approval_tasks history rather than being erased, so the Visual
 * Contract Workflow Tracker can render it as a collapsed "Previous attempt
 * #N".
 */
class ApprovalInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'workflow_id',
        'run_number',
        'current_step_id',
        'status',
        'draft_document_id',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'contract_id');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'current_step_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ApprovalTask::class, 'instance_id');
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function scopeAwaitingOutcome(Builder $query): Builder
    {
        return $query->whereIn('status', ['in_progress', 'revisions_required']);
    }
}
