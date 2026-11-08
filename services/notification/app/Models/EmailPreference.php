<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class EmailPreference extends Model
{
    use HasFactory;

    protected $table = 'email_preferences';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'email_notifications_enabled',
        'contract_expiry_alerts',
        'system_alerts_enabled',
        'sms_notifications_enabled',
        'login_alerts_enabled',
        'ai_risk_assessment_enabled',
        'ai_vendor_suggestions_enabled',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'email_notifications_enabled' => 'boolean',
        'contract_expiry_alerts' => 'boolean',
        'system_alerts_enabled' => 'boolean',
        'sms_notifications_enabled' => 'boolean',
        'login_alerts_enabled' => 'boolean',
        'ai_risk_assessment_enabled' => 'boolean',
        'ai_vendor_suggestions_enabled' => 'boolean',
    ];
}
