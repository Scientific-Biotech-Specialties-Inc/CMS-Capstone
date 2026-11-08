<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    use HasFactory;

    protected $table = 'contracts';
    protected $primaryKey = 'contract_id';

    protected $fillable = [
        'contract_code',
        'category_id',
        'supplier_id',
        'status_id',
        'bp_name',
        'sbu_number',
        'item_code',
        'description',
        'serial_number',
        'start_date',
        'end_date',
        'created_by',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'supplier_id' => 'integer',
        'status_id' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_by' => 'integer',
        'bp_name' => EncryptedCast::class,
        'description' => EncryptedCast::class,
    ];
}
