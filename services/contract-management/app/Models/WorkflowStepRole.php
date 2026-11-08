<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a WorkflowStep to one identity role held in auth-module.
 * auth_role_id is a soft reference (no real FK across services).
 * role_name_snapshot is cached at save time for display resilience and
 * for detecting "deleted" vs. "renamed" against a live lookup later
 * (decision #14).
 */
class WorkflowStepRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'step_id',
        'auth_role_id',
        'role_name_snapshot',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'step_id');
    }
}
