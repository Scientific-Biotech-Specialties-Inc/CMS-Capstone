<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    protected $primaryKey = 'audit_id';

    public $timestamps = false;

    protected $fillable = [
        'action',
        'entity_type',
        'entity_id',
        'user_id',
        'user_name',
        'user_email',
        'user_role',
        'user_department',
        'old_data',
        'new_data',
        'performed_at',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'performed_at' => 'datetime',
    ];
}
