<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One role's work item at one step of one approval_instance. A parallel
 * step produces one task per workflow_step_role; a sequential step
 * produces exactly one.
 *
 * acted_by_user_id / acted_via_delegation / delegation_id are soft
 * references into auth-module, resolved via its internal API at render
 * time rather than joined here — contract-management does not own user or
 * delegation data (Option C from the workflow engine plan).
 */
class ApprovalTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'instance_id',
        'step_id',
        'auth_role_id',
        'status',
        'acted_by_user_id',
        'acted_via_delegation',
        'delegation_id',
        'acted_at',
        'comment',
    ];

    protected $casts = [
        'acted_via_delegation' => 'boolean',
        'acted_at' => 'datetime',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class, 'instance_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'step_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isOnHold(): bool
    {
        return $this->status === 'on_hold';
    }
}
