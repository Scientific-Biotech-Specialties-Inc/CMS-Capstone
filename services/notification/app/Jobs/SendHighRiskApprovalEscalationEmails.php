<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\HighRiskApprovalEscalationMail;
use App\Models\EmailSendLog;
use App\Services\AuthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Fans out an email-only escalation for a High/Critical-risk contract
 * approval that has breached the 24-hour SLA window (US-023) to every user
 * in the given target role(s). No in-app notification, per the confirmed plan.
 */
final class SendHighRiskApprovalEscalationEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $targetRoles,
        public int $contractApprovalId,
        public int $contractId,
        public string $bpName,
        public string $riskLevel,
        public int $hoursOverdue,
    ) {}

    public function handle(AuthService $authService): void
    {
        $roles = array_filter(array_map('trim', explode(',', $this->targetRoles)));

        if (empty($roles)) {
            Log::warning('High-risk approval escalation triggered with empty target roles.', [
                'contract_approval_id' => $this->contractApprovalId,
            ]);
            return;
        }

        $users = $authService->getUsersByRoles($roles);

        if (empty($users)) {
            Log::info("No active users found for high-risk escalation target roles: {$this->targetRoles}", [
                'contract_approval_id' => $this->contractApprovalId,
            ]);
            return;
        }

        // Keyed on contract_approval_id so re-running the scheduled command
        // before a decision is made doesn't re-send duplicate emails to the
        // same user for the same overdue approval.
        $logicalNotificationId = $this->contractApprovalId;
        $subject = $this->getSubject();

        foreach ($users as $user) {
            $userId = (int) $user['id'];

            $alreadySent = EmailSendLog::where('user_id', $userId)
                ->where('notification_id', $logicalNotificationId)
                ->where('subject', $subject)
                ->where('status', 'sent')
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $recipientEmail = $user['email'] ?? null;
            if (!$recipientEmail) {
                continue;
            }

            $recipientName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            if (empty($recipientName)) {
                $recipientName = 'Manager';
            }

            try {
                Mail::to($recipientEmail)->send(new HighRiskApprovalEscalationMail(
                    $recipientName,
                    $this->contractId,
                    $this->bpName,
                    $this->riskLevel,
                    $this->hoursOverdue,
                ));

                EmailSendLog::create([
                    'notification_id' => $logicalNotificationId,
                    'user_id'         => $userId,
                    'recipient_email' => $recipientEmail,
                    'subject'         => $subject,
                    'status'          => 'sent',
                    'error_message'   => null,
                    'sent_at'         => now(),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send high-risk approval escalation email', [
                    'user_id'     => $userId,
                    'contract_id' => $this->contractId,
                    'error'       => $e->getMessage(),
                ]);

                EmailSendLog::create([
                    'notification_id' => $logicalNotificationId,
                    'user_id'         => $userId,
                    'recipient_email' => $recipientEmail,
                    'subject'         => $subject,
                    'status'          => 'failed',
                    'error_message'   => $e->getMessage(),
                    'sent_at'         => null,
                ]);
            }
        }
    }

    private function getSubject(): string
    {
        return '⚠️ SLA Breach: High-Risk Contract Awaiting Approval';
    }
}
