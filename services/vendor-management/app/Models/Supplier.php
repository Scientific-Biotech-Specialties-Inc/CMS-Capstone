<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $table = 'suppliers';
    protected $primaryKey = 'supplier_id';
    
    // Disable default timestamps because suppliers table only has created_at
    const UPDATED_AT = null;

    protected $fillable = [
        'supplier_name',
        'tin_number',
        'industry',
        'contact_person',
        'contact_number',
        'email',
        'address',
        'region',
        'status',
        'is_accredited',
        'is_flagged',
        'default_expense_category',
    ];

    protected $casts = [
        'contact_number' => EncryptedCast::class,
        'email' => EncryptedCast::class,
        'address' => EncryptedCast::class,
        'contact_person' => EncryptedCast::class,
        'is_accredited' => 'boolean',
        'is_flagged' => 'boolean',
    ];

    public function associations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VendorContractAssociation::class, 'vendor_id', 'supplier_id')
            ->where('vendor_type', 'supplier');
    }

    protected static function booted(): void
    {
        static::deleting(function (Supplier $supplier): void {
            VendorContractAssociation::where('vendor_type', 'supplier')
                ->where('vendor_id', $supplier->supplier_id)
                ->delete();
        });
    }
}
