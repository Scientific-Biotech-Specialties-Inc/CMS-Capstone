<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One position in a workflow's approval chain. mode = 'sequential' means
 * exactly one role must act before the next step; 'parallel' means every
 * role attached via workflowStepRoles must act (any holder of each role),
 * and a rejection by any one of them short-circuits the whole step
 * (spec 5.4 — the other pending roles' tasks become 'canceled').
 */
class WorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'order_index',
        'mode',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function workflowStepRoles(): HasMany
    {
        return $this->hasMany(WorkflowStepRole::class, 'step_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ApprovalTask::class, 'step_id');
    }

    public function isParallel(): bool
    {
        return $this->mode === 'parallel';
    }
}
