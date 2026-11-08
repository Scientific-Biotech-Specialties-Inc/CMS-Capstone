<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\ContractExpiryMail;
use App\Models\EmailPreference;
use App\Models\EmailSendLog;
use App\Models\SystemConfiguration;
use App\Services\AuthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Notification;


final class SendContractExpiryEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
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
        $userInfo = $authService->getUserInfo($this->userId);

        if (!$userInfo || empty($userInfo['email'])) {
            Log::warning("Cannot send contract expiry email. User info not found or email empty.", [
                'user_id' => $this->userId,
                'notification_id' => $this->notificationId,
            ]);
            return;
        }

        $recipientEmail = $userInfo['email'];
        $recipientName = trim(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? ''));
        if (empty($recipientName)) {
            $recipientName = 'User';
        }

        $role = $userInfo['role'] ?? null;
        $department = $userInfo['department'] ?? null;

        if (in_array($role, ['Admin', 'Manager'])) {
            if ($department !== 'Sales & Marketing') {
                Log::info("User {$this->userId} has role {$role} but department is {$department} (not Sales & Marketing). Skipping email send.");
                return;
            }
        }

        // Avoid sending duplicate emails for the same contract and threshold type to this user
        $alreadySent = EmailSendLog::where('user_id', $this->userId)
            ->where('status', 'sent')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
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
            Log::info("Email already sent for user {$this->userId} for contract {$this->contractId} (Type: {$this->notificationType}). Skipping email send.");
            $this->logToAuditLog($userInfo, 'skipped', 'Email already sent or skipped for this threshold.');
            $this->createNotification($userInfo, 'skipped', 'Email already sent or skipped for this threshold.');
            return;
        }

        // Check global System Configuration first
        $sysConfig = SystemConfiguration::first();
        if ($sysConfig) {
            if (!$sysConfig->email_notifs_enabled) {
                $this->logToAuditLog($userInfo, 'skipped', 'System-wide email notifications are disabled.');
                return;
            }

            if (!$sysConfig->contract_expiry_alerts && str_starts_with($this->notificationType, 'expiry_')) {
                $this->logToAuditLog($userInfo, 'skipped', 'System-wide contract expiry alerts are disabled.');
                return;
            }
        }

        // Check user preferences
        $pref = EmailPreference::where('user_id', $this->userId)->first();
        if ($pref) {
            if (!$pref->email_notifications_enabled) {
                $this->logToAuditLog($userInfo, 'skipped', 'User opted out of email notifications.');
                return;
            }

            if (!$pref->contract_expiry_alerts && str_starts_with($this->notificationType, 'expiry_')) {
                $this->logToAuditLog($userInfo, 'skipped', 'User opted out of contract expiry alerts.');
                return;
            }
        }

        try {
            $mailable = new ContractExpiryMail(
                $recipientName,
                $this->messageText,
                $this->notificationType,
                $this->contractId,
                $userInfo['role'] ?? null
            );

            Mail::to($recipientEmail)->send($mailable);

            EmailSendLog::create([
                'notification_id' => $this->notificationId,
                'user_id' => $this->userId,
                'recipient_email' => $recipientEmail,
                'subject' => $this->getSubject(),
                'status' => 'sent',
                'error_message' => null,
                'sent_at' => now(),
            ]);

            $this->logToAuditLog($userInfo, 'sent');
            $this->createNotification($userInfo, 'sent');
        } catch (\Exception $e) {
            Log::error("Failed to send contract expiry email via Brevo", [
                'user_id' => $this->userId,
                'notification_id' => $this->notificationId,
                'error' => $e->getMessage(),
            ]);

            EmailSendLog::create([
                'notification_id' => $this->notificationId,
                'user_id' => $this->userId,
                'recipient_email' => $recipientEmail,
                'subject' => $this->getSubject(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'sent_at' => null,
            ]);

            $this->logToAuditLog($userInfo, 'failed', $e->getMessage());
            $this->createNotification($userInfo, 'failed', $e->getMessage());
        }
    }

    /**
     * Send email send status to central audit logs.
     */
    private function logToAuditLog(array $userInfo, string $status, ?string $errorMessage = null): void
    {
        try {
            $contractServiceUrl = config('services.contract_management.url', 'http://contract-management:8000');
            $secret = config('app.internal_service_secret', '');

            $subject = $this->getSubject();
            $recipientEmail = $userInfo['email'] ?? 'unknown';
            $recipientName = trim(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? ''));
            if (empty($recipientName)) {
                $recipientName = $recipientEmail;
            }

            $action = match ($status) {
                'sent' => 'Email Sent',
                'skipped' => 'Email Skipped',
                'failed' => 'Email Failed',
                default => 'Email Sent',
            };

            $message = match ($status) {
                'sent' => "Email reminder sent to {$recipientEmail} for Contract #{$this->contractId}. Subject: \"{$subject}\".",
                'skipped' => "Email reminder skipped for {$recipientEmail} for Contract #{$this->contractId}. Reason: {$errorMessage}.",
                'failed' => "Failed to send email reminder to {$recipientEmail} for Contract #{$this->contractId}. Error: {$errorMessage}.",
                default => "Email reminder for Contract #{$this->contractId}.",
            };

            // Map user department to Sales or Finance to ensure central audit log visibility
            $userDept = $userInfo['department'] ?? null;
            if (empty($userDept) || !in_array($userDept, ['Sales & Marketing', 'Sales'])) {
                $role = $userInfo['role'] ?? '';
                if (str_contains(strtolower($role), 'finance') || str_contains(strtolower($role), 'sales')) {
                    $userDept = 'Sales & Marketing';
                } else {
                    $userDept = 'Sales';
                }
            }

            Http::withHeaders([
                'Accept' => 'application/json',
                'X-Internal-Secret' => $secret,
            ])->post("{$contractServiceUrl}/api/internal/audit-event", [
                'action' => $action,
                'entity_type' => 'Contract',
                'entity_id' => $this->contractId,
                'user_id' => $this->userId,
                'user_name' => $recipientName,
                'user_email' => $recipientEmail,
                'user_role' => $userInfo['role'] ?? 'Sales',
                'user_department' => $userDept,
                'old_data' => null,
                'new_data' => [
                    'message' => $message,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to write email audit log to contract service: " . $e->getMessage());
        }
    }

    /**
     * Create in-app notification for email status tracking.
     */
    private function createNotification(array $userInfo, string $status, ?string $errorMessage = null): void
    {
        try {
            $recipientEmail = $userInfo['email'] ?? 'unknown';

            $message = match ($status) {
                'sent' => "Email reminder for Contract #{$this->contractId} was successfully sent to {$recipientEmail}.",
                'skipped' => "Email reminder for Contract #{$this->contractId} has already been sent to {$recipientEmail}.",
                'failed' => "Email reminder for Contract #{$this->contractId} failed to send to {$recipientEmail}.",
                default => null,
            };

            if ($message) {
                // Ensure we don't insert duplicate notifications
                $exists = Notification::where('contract_id', $this->contractId)
                    ->where('notification_type', 'email_dispatch')
                    ->where('target_user_id', $this->userId)
                    ->where('message', $message)
                    ->exists();

                if (!$exists) {
                    Notification::create([
                        'contract_id' => $this->contractId,
                        'notification_type' => 'email_dispatch',
                        'target_user_id' => $this->userId,
                        'target_roles' => $userInfo['role'] ?? 'Sales',
                        'message' => $message,
                        'notification_date' => now(),
                        'is_read' => false,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to create email dispatch notification: " . $e->getMessage());
        }
    }

    /**
     * Get subject line helper.
     */
    private function getSubject(): string
    {
        return match ($this->notificationType) {
            'expiry_1' => '⚠️ URGENT: Contract Expiring Tomorrow',
            'expiry_30' => '📅 REMINDER: Contract Expiring in Less than 30 Days',
            'expiry_90' => 'ℹ️ NOTICE: Contract Expiring in 90 Days',
            default => '🔔 CMS Contract Expiry Notice',
        };
    }
}
