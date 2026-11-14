<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class VendorReimbursement extends Model
{
    protected $table = 'vendor_reimbursements';

    protected $fillable = [
        'vendor_type',
        'vendor_id',
        'serms_reimbursement_id',
        'reference_no',
        'tin_number',
        'vendor_name',
        'category',
        'description',
        'amount',
        'status',
        'employee_name',
        'contract_id',
        'expense_date',
        'serms_created_at',
        'synced_at',
    ];

    protected $casts = [
        'vendor_id'              => 'integer',
        'serms_reimbursement_id' => 'integer',
        'amount'                 => 'float',
        'contract_id'            => 'integer',
        'expense_date'           => 'date',
        'serms_created_at'       => 'datetime',
        'synced_at'              => 'datetime',
    ];
}
