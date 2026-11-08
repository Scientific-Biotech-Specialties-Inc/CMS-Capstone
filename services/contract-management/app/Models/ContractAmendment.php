<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractAmendment extends Model
{
    use HasFactory;

    protected $table = 'contract_amendments';
    protected $primaryKey = 'amendment_id';

    protected $fillable = [
        'contract_id',
        'bp_name',
        'category',
        'item_code',
        'description',
        'serial_number',
        'sbu_number',
        'region',
        'start_date',
        'end_date',
        'reason',
        'status',
        'request_date',
        'version',
        'created_by',
        'approved_by',
        'rejection_reason',
        'document_ids',
    ];

    protected $casts = [
        'bp_name' => EncryptedCast::class,
        'description' => EncryptedCast::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'request_date' => 'date',
        'document_ids' => 'array',
        'version' => 'integer',
        'contract_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'contract_id');
    }
}
