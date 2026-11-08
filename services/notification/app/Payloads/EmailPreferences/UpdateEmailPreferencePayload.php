<?php

declare(strict_types=1);

namespace App\Payloads\EmailPreferences;

final readonly class UpdateEmailPreferencePayload
{
    public function __construct(
        public bool $emailNotificationsEnabled,
        public bool $contractExpiryAlerts,
        public bool $systemAlertsEnabled,
        public bool $smsNotificationsEnabled,
        public bool $loginAlertsEnabled,
        public bool $aiRiskAssessmentEnabled = true,
        public bool $aiVendorSuggestionsEnabled = true,
    ) {}
}
