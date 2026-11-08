<?php

declare(strict_types=1);

namespace App\Actions\EmailPreferences;

use App\Models\EmailPreference;
use App\Models\AuditLog;
use App\Payloads\EmailPreferences\UpdateEmailPreferencePayload;

final readonly class UpdateEmailPreference
{
    /**
     * Update or create the email preference for the given user.
     */
    public function handle(int $userId, UpdateEmailPreferencePayload $payload, ?array $actor = null): EmailPreference
    {
        $oldPreference = EmailPreference::where('user_id', $userId)->first();
        $oldData = $oldPreference ? [
            'email_notifications_enabled' => (bool)$oldPreference->email_notifications_enabled,
            'contract_expiry_alerts' => (bool)$oldPreference->contract_expiry_alerts,
            'system_alerts_enabled' => (bool)$oldPreference->system_alerts_enabled,
            'sms_notifications_enabled' => (bool)$oldPreference->sms_notifications_enabled,
            'login_alerts_enabled' => (bool)$oldPreference->login_alerts_enabled,
            'ai_risk_assessment_enabled' => (bool)$oldPreference->ai_risk_assessment_enabled,
            'ai_vendor_suggestions_enabled' => (bool)$oldPreference->ai_vendor_suggestions_enabled,
        ] : null;

        $preference = EmailPreference::updateOrCreate(
            ['user_id' => $userId],
            [
                'email_notifications_enabled' => $payload->emailNotificationsEnabled,
                'contract_expiry_alerts' => $payload->contractExpiryAlerts,
                'system_alerts_enabled' => $payload->systemAlertsEnabled,
                'sms_notifications_enabled' => $payload->smsNotificationsEnabled,
                'login_alerts_enabled' => $payload->loginAlertsEnabled,
                'ai_risk_assessment_enabled' => $payload->aiRiskAssessmentEnabled,
                'ai_vendor_suggestions_enabled' => $payload->aiVendorSuggestionsEnabled,
            ]
        );

        $newData = [
            'email_notifications_enabled' => (bool)$payload->emailNotificationsEnabled,
            'contract_expiry_alerts' => (bool)$payload->contractExpiryAlerts,
            'system_alerts_enabled' => (bool)$payload->systemAlertsEnabled,
            'sms_notifications_enabled' => (bool)$payload->smsNotificationsEnabled,
            'login_alerts_enabled' => (bool)$payload->loginAlertsEnabled,
            'ai_risk_assessment_enabled' => (bool)$payload->aiRiskAssessmentEnabled,
            'ai_vendor_suggestions_enabled' => (bool)$payload->aiVendorSuggestionsEnabled,
        ];

        $name = $actor['name'] ?? null;
        $email = $actor['email'] ?? null;
        $role = $actor['role'] ?? null;
        $department = $actor['department'] ?? null;

        // Fallback lookup via AuthService if email or name is empty
        if (empty($name) || empty($email)) {
            try {
                $authService = app(\App\Services\AuthService::class);
                $userInfo = $authService->getUserInfo($userId);
                if ($userInfo) {
                    $firstName = $userInfo['profile']['first_name'] ?? '';
                    $lastName = $userInfo['profile']['last_name'] ?? '';
                    $fullName = trim("{$firstName} {$lastName}");
                    if (empty($name)) {
                        $name = !empty($fullName) ? $fullName : ($userInfo['email'] ?? null);
                    }
                    if (empty($email)) {
                        $email = $userInfo['email'] ?? null;
                    }
                    if (empty($role)) {
                        $role = $userInfo['profile']['role']['name'] ?? null;
                    }
                    if (empty($department)) {
                        $department = $userInfo['profile']['department']['name'] ?? null;
                    }
                }
            } catch (\Exception $e) {
                // Ignore fallback connection errors
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('audit_logs')) {
            AuditLog::create([
                'action' => 'email_preferences_updated',
                'entity_type' => 'EmailPreference',
                'entity_id' => 0,
                'user_id' => $userId,
                'user_name' => $name,
                'user_email' => $email,
                'user_role' => $role,
                'user_department' => $department,
                'old_data' => $oldData,
                'new_data' => $newData,
                'performed_at' => now(),
            ]);
        }

        return $preference;
    }
}
