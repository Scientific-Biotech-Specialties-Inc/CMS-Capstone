<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuggestedVendor extends Model
{
    protected $table = 'suggested_vendors';
    protected $primaryKey = 'suggested_vendor_id';

    protected $fillable = [
        'vendor_name',
        'tin_number',
        'industry',
        'contact_person',
        'contact_number',
        'email',
        'address',
        'region',
        'default_expense_category',
        'source_system',
        'source_receipt_id',
        'submitted_by',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'created_supplier_id',
    ];

    protected $casts = [
        'submitted_by'        => 'integer',
        'reviewed_by'         => 'integer',
        'reviewed_at'         => 'datetime',
        'created_supplier_id' => 'integer',
    ];
}
