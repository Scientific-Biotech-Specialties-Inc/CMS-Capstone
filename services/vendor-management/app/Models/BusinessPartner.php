<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessPartner extends Model
{
    use HasFactory;

    public const CLASS_TIERS = ['Class A', 'Class B', 'Class C'];

    protected $table = 'business_partners';
    protected $primaryKey = 'partner_id';

    protected $fillable = [
        'bp_code',
        'prs_institution_id',
        'partner_name',
        'industry',
        'contact_person',
        'contact_number',
        'email',
        'address',
        'region',
        'class_tier',
        'status',
        'created_by',
    ];

    protected $casts = [
        'prs_institution_id' => 'integer',
        'contact_number'     => EncryptedCast::class,
        'email'              => EncryptedCast::class,
        'address'            => EncryptedCast::class,
        'contact_person'     => EncryptedCast::class,
        'created_by'         => 'integer',
    ];

    public static function normalizeClassTier(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = strtoupper(trim($value));

        return match ($trimmed) {
            'A', 'CLASS A', 'CLASS_A', 'CLASS-A' => 'Class A',
            'B', 'CLASS B', 'CLASS_B', 'CLASS-B' => 'Class B',
            'C', 'CLASS C', 'CLASS_C', 'CLASS-C' => 'Class C',
            default => null,
        };
    }

    public function associations(): HasMany
    {
        return $this->hasMany(VendorContractAssociation::class, 'vendor_id', 'partner_id')
            ->where('vendor_type', 'partner');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(PartnerBranch::class, 'partner_id', 'partner_id');
    }

    public function toPrsInstitutionPayload(): array
    {
        $branches = $this->relationLoaded('branches') ? $this->branches : $this->branches()->get();

        return [
            'id'                    => (int) $this->partner_id,
            'institution_uid'       => $this->bp_code,
            'source_institution_id' => $this->bp_code,
            'bp_code'               => $this->bp_code,
            'prs_institution_id'    => $this->prs_institution_id !== null ? (int) $this->prs_institution_id : null,
            'institution_name'      => $this->partner_name,
            'address'               => $this->address,
            'class_tier'            => $this->class_tier ?: 'Class C',
            'is_active'             => ($this->status ?? 'Active') === 'Active',
            'status'                => $this->status ?? 'Active',
            'region'                => $this->region,
            'locations'             => $branches->map(fn (PartnerBranch $b): array => [
                'branch_id'     => (int) $b->branch_id,
                'location_uid'  => $b->location_uid,
                'prs_branch_id' => $b->prs_branch_id !== null ? (int) $b->prs_branch_id : null,
                'branch_name'   => $b->branch_name,
                'address'       => $b->address,
                'region'        => $b->region,
                'sbsi_branch'   => $b->sbsi_branch ?: 'Main Office',
                'is_primary'    => (bool) $b->is_primary,
                'is_active'     => (bool) $b->is_active,
            ])->values()->all(),
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (BusinessPartner $partner): void {
            VendorContractAssociation::where('vendor_type', 'partner')
                ->where('vendor_id', $partner->partner_id)
                ->delete();
        });
    }
}
