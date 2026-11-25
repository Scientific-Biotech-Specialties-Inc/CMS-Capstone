<?php

declare(strict_types=1);

namespace App\Services;

final class PrsContractStatusMapper
{
    /**
     * Map an incoming PRS status or outcome into CMS approval & workflow statuses.
     *
     * @return array{prs_status: string, outcome: string, approval_status: string, workflow_status: ?string}
     */
    public function map(?string $prsStatus, ?string $outcome): array
    {
        $raw = trim((string) ($prsStatus ?: $outcome ?: 'Pending'));
        $normalized = strtolower($raw);

        return match ($normalized) {
            'won', 'approved', 'active', 'closed won', 'closed_won' => [
                'prs_status'      => $raw,
                'outcome'         => 'Won',
                'approval_status' => 'Approved',
                'workflow_status' => 'SBSI Review',
            ],
            'signed' => [
                'prs_status'      => $raw,
                'outcome'         => 'Won',
                'approval_status' => 'Approved',
                'workflow_status' => 'Signed',
            ],
            'loss', 'lost', 'rejected', 'cancelled', 'canceled', 'closed lost', 'closed_lost' => [
                'prs_status'      => $raw,
                'outcome'         => 'Loss',
                'approval_status' => 'Rejected',
                'workflow_status' => null,
            ],
            default => [
                'prs_status'      => $raw,
                'outcome'         => 'Follow-up',
                'approval_status' => 'Pending',
                'workflow_status' => null,
            ],
        };
    }
}
