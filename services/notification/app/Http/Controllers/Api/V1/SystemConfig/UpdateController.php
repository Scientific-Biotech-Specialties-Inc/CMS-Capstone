<?php

namespace App\Http\Controllers\Api\V1\SystemConfig;

use App\Http\Controllers\Controller;
use App\Models\SystemConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // System-wide notification settings are admin-only. The route has no
        // role middleware in this service, so enforce it here (mirrors the
        // Admin/Super Admin guard on the SPA's system-config page).
        $role = $request->get('auth_role');
        if (!in_array($role, ['Admin', 'Super Admin'], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'email_notifs_enabled' => 'nullable|boolean',
            'in_app_notifs_enabled' => 'nullable|boolean',
            'contract_expiry_alerts' => 'nullable|boolean',
            'approval_alerts' => 'nullable|boolean',
            'renewal_reminders' => 'nullable|boolean',
        ]);

        $config = SystemConfiguration::first();
        if (!$config) {
            $config = new SystemConfiguration();
        }

        $config->fill($validated);
        $config->save();

        return response()->json([
            'message' => 'System configuration updated successfully.',
            'data' => $config,
        ]);
    }
}
