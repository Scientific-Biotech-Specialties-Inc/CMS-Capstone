<?php

namespace App\Http\Controllers\Api\V1\SystemConfig;

use App\Http\Controllers\Controller;
use App\Models\SystemConfiguration;
use Illuminate\Http\JsonResponse;

class ShowController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $config = SystemConfiguration::first();

        if (!$config) {
            $config = SystemConfiguration::create([
                'email_notifs_enabled' => true,
                'in_app_notifs_enabled' => true,
                'contract_expiry_alerts' => true,
                'approval_alerts' => true,
                'renewal_reminders' => true,
            ]);
        }

        return response()->json([
            'message' => 'System configuration retrieved successfully.',
            'data' => $config,
        ]);
    }
}
