<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorContractAssociation extends Model
{
    use HasFactory;

    protected $table = 'vendor_contract_associations';

    protected $fillable = [
        'vendor_type',
        'vendor_id',
        'contract_id',
        'attached_by',
    ];

    protected $casts = [
        'vendor_id' => 'integer',
        'contract_id' => 'integer',
        'attached_by' => 'integer',
    ];

    /**
     * Scope to filter associations by vendor type and ID.
     */
    public function scopeForVendor(Builder $query, string $vendorType, int $vendorId): Builder
    {
        return $query->where('vendor_type', $vendorType)
                     ->where('vendor_id', $vendorId);
    }

    /**
     * Scope to filter associations by the user who attached them (e.g. Sales user scope).
     */
    public function scopeAttachedBy(Builder $query, int $attachedBy): Builder
    {
        return $query->where('attached_by', $attachedBy);
    }

    /**
     * Get the contract associated with this record.
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'contract_id');
    }
}
