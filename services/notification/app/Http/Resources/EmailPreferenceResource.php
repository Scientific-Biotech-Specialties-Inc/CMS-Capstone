<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $user_id
 * @property-read bool $email_notifications_enabled
 * @property-read bool $contract_expiry_alerts
 */
final class EmailPreferenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'email_notifications_enabled' => $this->email_notifications_enabled,
            'contract_expiry_alerts' => $this->contract_expiry_alerts,
            'system_alerts_enabled' => $this->system_alerts_enabled,
            'sms_notifications_enabled' => $this->sms_notifications_enabled,
            'login_alerts_enabled' => $this->login_alerts_enabled,
            'ai_risk_assessment_enabled' => $this->ai_risk_assessment_enabled,
            'ai_vendor_suggestions_enabled' => $this->ai_vendor_suggestions_enabled,
        ];
    }
}
