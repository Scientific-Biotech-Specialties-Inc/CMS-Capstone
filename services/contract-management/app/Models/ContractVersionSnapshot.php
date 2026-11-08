<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractVersionSnapshot extends Model
{
    use HasFactory;

    protected $table = 'contract_version_snapshots';

    protected $fillable = [
        'contract_id',
        'version',
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
        'amended_by',
        'approved_by',
        'approved_date',
        'docs',
    ];

    protected $casts = [
        'bp_name' => EncryptedCast::class,
        'description' => EncryptedCast::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_date' => 'date',
        'docs' => 'array',
        'version' => 'integer',
        'contract_id' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'contract_id');
    }
}
