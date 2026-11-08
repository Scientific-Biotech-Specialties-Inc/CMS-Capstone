<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\EmailLogs;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmailSendLogResource;
use App\Models\EmailSendLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class IndexController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $userId = (int) $request->input('auth_id');

        $logs = EmailSendLog::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(15);

        return EmailSendLogResource::collection($logs);
    }
}
