<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ContractExecutionEvent extends Model
{
    protected $fillable = [
        'contract_id', 'operation_id', 'action', 'stage', 'state', 'draft_document_id',
        'document_id', 'submitted_by', 'decided_by', 'decided_at', 'reason',
    ];

    protected $casts = ['decided_at' => 'datetime'];
}
