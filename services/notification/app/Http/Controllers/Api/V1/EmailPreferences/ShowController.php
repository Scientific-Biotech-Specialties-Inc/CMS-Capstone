<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\EmailPreferences;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmailPreferenceResource;
use App\Models\EmailPreference;
use Illuminate\Http\Request;

final class ShowController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): EmailPreferenceResource
    {
        $userId = (int) $request->input('auth_id');

        $preference = EmailPreference::firstOrCreate(
            ['user_id' => $userId],
            [
                'email_notifications_enabled' => true,
                'contract_expiry_alerts' => true,
                'system_alerts_enabled' => true,
                'sms_notifications_enabled' => false,
                'login_alerts_enabled' => true,
            ]
        );

        $preference->wasRecentlyCreated = false;

        return new EmailPreferenceResource($preference);
    }
}
