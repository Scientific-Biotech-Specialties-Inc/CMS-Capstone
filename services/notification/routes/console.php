<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mail:test {email}', function (string $email) {
    $this->info("Sending test email to {$email}...");
    
    try {
        \Illuminate\Support\Facades\Mail::raw('This is a test email sent from CMS Capstone using Brevo!', function ($message) use ($email) {
            $message->to($email)
                    ->subject('CMS Brevo SMTP Test');
        });
        $this->info("Test email successfully dispatched!");
    } catch (\Exception $e) {
        $this->error("Failed to send email: " . $e->getMessage());
    }
})->purpose('Send a test email using configured SMTP credentials');

Artisan::command('notifications:queue-past-emails', function () {
    $this->info("Checking for past notifications without sent email logs...");

    $notifications = \App\Models\Notification::all();
    $dispatchedCount = 0;
    $authService = app(\App\Services\AuthService::class);

    foreach ($notifications as $notification) {
        if ($notification->target_user_id !== null) {
            $userId = (int) $notification->target_user_id;

            // Check if already sent for this specific user, contract, and type
            $logExists = \App\Models\EmailSendLog::where('user_id', $userId)
                ->where('status', 'sent')
                ->whereExists(function ($query) use ($notification) {
                    $query->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('notifications')
                        ->whereColumn('notifications.notification_id', 'email_send_logs.notification_id')
                        ->where('notifications.notification_type', $notification->notification_type);
                    
                    if ($notification->contract_id !== null) {
                        $query->where('notifications.contract_id', $notification->contract_id);
                    } else {
                        $query->whereNull('notifications.contract_id');
                    }
                })
                ->exists();

            if (!$logExists) {
                $this->info("Dispatching email for notification ID: {$notification->notification_id} (Type: {$notification->notification_type}) to User: {$userId}");

                \App\Jobs\SendContractExpiryEmail::dispatch(
                    $userId,
                    (int) $notification->notification_id,
                    $notification->contract_id ? (int) $notification->contract_id : null,
                    $notification->message,
                    $notification->notification_type
                );
                $dispatchedCount++;
            }
        } else {
            // Broadcast notification: check for each user matching target roles
            $roles = array_filter(array_map('trim', explode(',', $notification->target_roles)));
            if (empty($roles)) {
                continue;
            }

            $users = $authService->getUsersByRoles($roles);
            foreach ($users as $user) {
                $userId = (int) $user['id'];

                $logExists = \App\Models\EmailSendLog::where('user_id', $userId)
                    ->where('status', 'sent')
                    ->whereExists(function ($query) use ($notification) {
                        $query->select(\Illuminate\Support\Facades\DB::raw(1))
                            ->from('notifications')
                            ->whereColumn('notifications.notification_id', 'email_send_logs.notification_id')
                            ->where('notifications.notification_type', $notification->notification_type);
                        
                        if ($notification->contract_id !== null) {
                            $query->where('notifications.contract_id', $notification->contract_id);
                        } else {
                            $query->whereNull('notifications.contract_id');
                        }
                    })
                    ->exists();

                if (!$logExists) {
                    $this->info("Dispatching broadcast email for notification ID: {$notification->notification_id} (Type: {$notification->notification_type}) to User: {$userId}");

                    \App\Jobs\SendContractExpiryEmail::dispatch(
                        $userId,
                        (int) $notification->notification_id,
                        $notification->contract_id ? (int) $notification->contract_id : null,
                        $notification->message,
                        $notification->notification_type
                    );
                    $dispatchedCount++;
                }
            }
        }
    }

    $this->info("Successfully dispatched {$dispatchedCount} email jobs for past notifications.");
})->purpose('Queue emails for past notifications that have not been sent yet');
