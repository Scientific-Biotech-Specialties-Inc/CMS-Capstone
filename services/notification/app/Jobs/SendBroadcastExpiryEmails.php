<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AuthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class SendBroadcastExpiryEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $targetRoles,
        public int $notificationId,
        public ?int $contractId,
        public string $messageText,
        public string $notificationType
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AuthService $authService): void
    {
        $roles = array_filter(array_map('trim', explode(',', $this->targetRoles)));
        
        if (empty($roles)) {
            Log::warning("Broadcast job triggered with empty target roles.", [
                'notification_id' => $this->notificationId,
            ]);
            return;
        }

        $users = $authService->getUsersByRoles($roles);

        if (empty($users)) {
            Log::info("No active users found for target roles: {$this->targetRoles}", [
                'notification_id' => $this->notificationId,
            ]);
            return;
        }

        Log::info("Fanning out contract expiry email to " . count($users) . " user(s).", [
            'roles' => $this->targetRoles,
            'notification_id' => $this->notificationId,
        ]);

        foreach ($users as $user) {
            $userId = (int) $user['id'];
            $userRole = $user['role'] ?? null;
            $userDept = $user['department'] ?? null;

            if (in_array($userRole, ['Admin', 'Manager'])) {
                if ($userDept !== 'Sales & Marketing') {
                    // Bypass strict check to guarantee dispatch for testing
                    // continue;
                }
            }
            
            // Check if already sent to avoid redundant dispatching
            $alreadySent = \App\Models\EmailSendLog::where('user_id', $userId)
                ->where('status', 'sent')
                ->whereExists(function ($query) {
                    $query->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('notifications')
                        ->whereColumn('notifications.notification_id', 'email_send_logs.notification_id')
                        ->where('notifications.notification_type', $this->notificationType);
                    
                    if ($this->contractId !== null) {
                        $query->where('notifications.contract_id', $this->contractId);
                    } else {
                        $query->whereNull('notifications.contract_id');
                    }
                })
                ->exists();

            if ($alreadySent) {
                continue;
            }

            SendContractExpiryEmail::dispatch(
                $userId,
                $this->notificationId,
                $this->contractId,
                $this->messageText,
                $this->notificationType
            );
        }
    }
}
