<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_notifs_enabled',
        'in_app_notifs_enabled',
        'contract_expiry_alerts',
        'approval_alerts',
        'renewal_reminders',
    ];

    protected $casts = [
        'email_notifs_enabled' => 'boolean',
        'in_app_notifs_enabled' => 'boolean',
        'contract_expiry_alerts' => 'boolean',
        'approval_alerts' => 'boolean',
        'renewal_reminders' => 'boolean',
    ];
}
