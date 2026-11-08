<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a High/Critical-risk contract's mandatory approval (US-023) has
 * been pending for more than the 24-hour SLA window without a recorded
 * decision. Email-only escalation, per the confirmed implementation plan.
 */
final class HighRiskApprovalEscalationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public int $contractId,
        public string $bpName,
        public string $riskLevel,
        public int $hoursOverdue,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⚠️ SLA Breach: High-Risk Contract Awaiting Approval',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.high_risk_approval_escalation',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
