<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessPartner extends Model
{
    use HasFactory;

    protected $table = 'business_partners';
    protected $primaryKey = 'partner_id';

    protected $fillable = [
        'bp_code',
        'partner_name',
        'industry',
        'contact_person',
        'contact_number',
        'email',
        'address',
        'region',
        'status',
        'created_by',
        'industry',
        'status',
        'contact_person',
    ];

    protected $casts = [
        'contact_number' => EncryptedCast::class,
        'email' => EncryptedCast::class,
        'address' => EncryptedCast::class,
        'contact_person' => EncryptedCast::class,
        'created_by' => 'integer',
    ];

    public function associations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VendorContractAssociation::class, 'vendor_id', 'partner_id')
            ->where('vendor_type', 'partner');
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
