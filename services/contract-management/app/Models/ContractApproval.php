<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks the mandatory approval gate for High/Critical-risk contracts (US-023).
 * A contract cannot move to the "Active" workflow status while it has an
 * unresolved (pending) High/Critical flag with no "approved" decision row.
 */
class ContractApproval extends Model
{
    use HasFactory;

    protected $table = 'contract_approvals';

    protected $fillable = [
        'contract_id',
        'risk_level',
        'approver_id',
        'rationale',
        'decision',
        'decided_at',
        'flagged_at',
        'sla_due_at',
        'escalated_at',
    ];

    protected $casts = [
        'decided_at'   => 'datetime',
        'flagged_at'   => 'datetime',
        'sla_due_at'   => 'datetime',
        'escalated_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'contract_id');
    }

    public function isPending(): bool
    {
        return $this->decision === null;
    }

    public function isOverdue(): bool
    {
        return $this->isPending() && $this->sla_due_at?->isPast();
    }
}
