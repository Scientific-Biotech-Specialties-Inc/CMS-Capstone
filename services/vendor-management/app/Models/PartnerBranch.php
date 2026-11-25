<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerBranch extends Model
{
    use HasFactory;

    protected $table = 'partner_branches';
    protected $primaryKey = 'branch_id';

    protected $fillable = [
        'location_uid',
        'partner_id',
        'prs_branch_id',
        'branch_name',
        'address',
        'region',
        'sbsi_branch',
        'is_primary',
        'is_active',
    ];

    protected $casts = [
        'partner_id'    => 'integer',
        'prs_branch_id' => 'integer',
        'address'       => EncryptedCast::class,
        'is_primary'    => 'boolean',
        'is_active'     => 'boolean',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(BusinessPartner::class, 'partner_id', 'partner_id');
    }

    public function toPrsLocationPayload(): array
    {
        return [
            'branch_id'        => (int) $this->branch_id,
            'location_uid'     => $this->location_uid,
            'prs_branch_id'    => $this->prs_branch_id !== null ? (int) $this->prs_branch_id : null,
            'partner_id'       => (int) $this->partner_id,
            'institution_uid'  => $this->partner?->bp_code,
            'institution_name' => $this->partner?->partner_name,
            'branch_name'      => $this->branch_name,
            'address'          => $this->address,
            'region'           => $this->region,
            'sbsi_branch'      => $this->sbsi_branch ?: 'Main Office',
            'is_primary'       => (bool) $this->is_primary,
            'is_active'        => (bool) $this->is_active,
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
