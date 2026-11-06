<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogService
{
    /**
     * Log an action to the audit logs.
     */
    public function log(string $action, string $entityType, string|int $entityId, ?int $userId, array $old = [], array $new = [], ?string $department = null): void
    {
        $sensitiveFields = ['contact_number', 'email', 'address'];
        
        $oldClean = $this->cleanData($old, $sensitiveFields);
        $newClean = $this->cleanData($new, $sensitiveFields);

        $userName = null;
        $userEmail = null;
        $userRole = null;
        $userDept = $department;

        $request = request();
        if ($request) {
            $authUser = $request->get('auth_user');
            if ($authUser) {
                $firstName = $authUser['first_name'] ?? '';
                $lastName = $authUser['last_name'] ?? '';
                $userName = trim("{$firstName} {$lastName}");
                if (empty($userName)) {
                    $userName = $authUser['email'] ?? null;
                }
                $userEmail = $authUser['email'] ?? null;
                $userRole = $authUser['role'] ?? null;
                $userDept = $userDept ?? ($authUser['department'] ?? null);
            }
        }

        if ($userDept !== 'Sales & Marketing') {
            // OWASP A09: Privileged non-Sales events (Admin, Manager, Finance, etc.)
            // are logged to the dedicated security channel so administrative actions
            // are preserved on disk without altering DB audit queries.
            \Illuminate\Support\Facades\Log::channel('security')->info('Privileged action audit event', [
                'action'          => $action,
                'entity_type'     => $entityType,
                'entity_id'       => $entityId,
                'user_id'         => $userId,
                'user_name'       => $userName,
                'user_email'      => $userEmail,
                'user_role'       => $userRole,
                'user_department' => $userDept,
                'old_data'        => !empty($oldClean) ? $oldClean : null,
                'new_data'        => !empty($newClean) ? $newClean : null,
                'performed_at'    => now()->toIso8601String(),
            ]);
            return;
        }

        AuditLog::create([
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $userId,
            'user_name' => $userName,
            'user_email' => $userEmail,
            'user_role' => $userRole,
            'user_department' => $userDept,
            'old_data' => !empty($oldClean) ? $oldClean : null,
            'new_data' => !empty($newClean) ? $newClean : null,
            'performed_at' => now(),
        ]);
    }

    /**
     * Clean sensitive fields from data array by replacing them with [REDACTED].
     */
    protected function cleanData(array $data, array $sensitiveFields): array
    {
        foreach ($sensitiveFields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = '[REDACTED]';
            }
        }
        return $data;
    }
}
