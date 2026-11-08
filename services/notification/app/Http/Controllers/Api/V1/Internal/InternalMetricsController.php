<?php

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Models\EmailSendLog;
use App\Models\Notification;
use Illuminate\Http\Request;

/**
 * Internal, X-Internal-Secret-authenticated metrics snapshot for
 * analytics-service's descriptive aggregation (Feature 4).
 */
class InternalMetricsController extends Controller
{
    public function notifications(Request $request)
    {
        $totalSent = EmailSendLog::where('status', 'sent')->count();
        $totalFailed = EmailSendLog::where('status', 'failed')->count();
        $totalAttempts = $totalSent + $totalFailed;

        return response()->json([
            'total_notifications'      => Notification::count(),
            'total_emails_sent'        => $totalSent,
            'total_emails_failed'      => $totalFailed,
            'email_success_rate_pct'   => $totalAttempts > 0 ? round(($totalSent / $totalAttempts) * 100, 2) : null,
            'notifications_by_type'    => Notification::select('notification_type')->get()->countBy('notification_type'),
        ]);
    }
}
