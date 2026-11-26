<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailPreference;
use App\Models\Notification;
use App\Models\NotificationRead;
use App\Models\SystemConfiguration;

final class NotificationAudienceMatcher
{
    /**
     * Checks whether in-app alerts are enabled globally and for the given user.
     */
    public function isUserAlertsEnabled(int $userId): bool
    {
        $sysConfig = SystemConfiguration::first();
        if ($sysConfig && !$sysConfig->in_app_notifs_enabled) {
            return false;
        }

        $preference = EmailPreference::where('user_id', $userId)->first();

        return $preference ? (bool) $preference->system_alerts_enabled : true;
    }

    /**
     * Checks whether a notification (Eloquent model or decoded event payload)
     * should be visible to a user with the given role and ID.
     *
     * @param Notification|array<string, mixed> $notification
     */
    public function matches(Notification|array $notification, string $userRole, int $userId): bool
    {
        $targetRoles = is_array($notification)
            ? (string) ($notification['target_roles'] ?? '')
            : (string) ($notification->target_roles ?? '');

        if ($targetRoles === '') {
            return false;
        }

        $roles = array_map('trim', explode(',', $targetRoles));
        $isAdminRole = in_array($userRole, ['Admin', 'Super Admin', 'CEO'], true);
        $matchesRole = in_array($userRole, $roles, true)
            || ($isAdminRole && in_array('Admin', $roles, true));

        if (!$matchesRole) {
            return false;
        }

        $targetUserId = is_array($notification)
            ? ($notification['target_user_id'] ?? null)
            : $notification->target_user_id;

        if ($targetUserId !== null && (int) $targetUserId !== $userId) {
            return false;
        }

        return true;
    }

    /**
     * Formats a Notification model into the canonical JSON payload returned to the SPA.
     *
     * @return array<string, mixed>
     */
    public function formatForClient(Notification $notification, ?NotificationRead $read = null): array
    {
        return [
            'id'                => (string) $notification->notification_id,
            'contract_id'       => $notification->contract_id,
            'notification_type' => $notification->notification_type,
            'message'           => $notification->message,
            'notification_date' => $notification->notification_date?->toISOString(),
            'is_read'           => $read?->is_read ?? false,
            'is_archived'       => $read?->is_archived ?? false,
            'is_favorite'       => $read?->is_favorite ?? false,
        ];
    }

    /**
     * Formats a Notification model for internal Redis Pub/Sub distribution,
     * including audience metadata so each SSE worker filters securely per user.
     *
     * @return array<string, mixed>
     */
    public function formatForBroadcast(Notification $notification): array
    {
        return [
            ...$this->formatForClient($notification),
            'target_roles'   => $notification->target_roles,
            'target_user_id' => $notification->target_user_id,
        ];
    }
}
