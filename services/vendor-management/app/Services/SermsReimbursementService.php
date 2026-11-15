<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VendorReimbursement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class SermsReimbursementService
{
    /**
     * Fetches related reimbursements from SERMS and upserts them into CMS.
     * Falls back to stored CMS rows if SERMS is unreachable.
     *
     * @return array{serms_connected: bool, items: Collection<int, VendorReimbursement>, synced_at: ?string}
     */
    public function syncForVendor(string $vendorType, int $vendorId, ?string $tin, string $vendorName): array
    {
        $sermsConnected = false;
        $baseUrl = rtrim((string) config('services.serms.url'), '/');
        $secret = (string) config('services.serms.secret');

        $queries = [];
        $cleanTin = $tin !== null ? trim($tin) : '';
        $cleanName = trim($vendorName);

        // Query TIN and vendor_name separately: SERMS restricts by TIN when both
        // are passed together, which hides same-merchant receipts with branch/OCR TINs.
        if ($cleanTin !== '') {
            $queries[] = ['tin' => $cleanTin];
        }
        if ($cleanName !== '') {
            $queries[] = ['vendor_name' => $cleanName];
        }

        if ($baseUrl !== '') {
            foreach ($queries as $query) {
                $rows = $this->fetchRows($baseUrl, $secret, $query, $vendorType, $vendorId);
                if ($rows !== null) {
                    $sermsConnected = true;
                    $this->upsertRows($vendorType, $vendorId, $rows);
                }
            }
        }

        $items = VendorReimbursement::query()
            ->where('vendor_type', $vendorType)
            ->where('vendor_id', $vendorId)
            ->orderByDesc('expense_date')
            ->orderByDesc('serms_reimbursement_id')
            ->get();

        $latestSync = $items->max('synced_at');

        return [
            'serms_connected' => $sermsConnected,
            'items'           => $items,
            'synced_at'       => $latestSync ? Carbon::parse($latestSync)->toISOString() : null,
        ];
    }

    /**
     * @param array<string, string> $query
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchRows(string $baseUrl, string $secret, array $query, string $vendorType, int $vendorId): ?array
    {
        try {
            $response = Http::connectTimeout(3)
                ->timeout(5)
                ->withHeaders([
                    'Accept'            => 'application/json',
                    'X-Internal-Secret' => $secret,
                ])
                ->get("{$baseUrl}/internal/reimbursements/by-vendor", $query);

            if ($response->successful()) {
                $rows = $response->json('data');
                return is_array($rows) ? $rows : [];
            }

            Log::warning('SermsReimbursementService: non-2xx response from SERMS', [
                'status' => $response->status(),
                'vendor' => "{$vendorType}:{$vendorId}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('SermsReimbursementService: SERMS unreachable, serving stored history', [
                'error'  => $e->getMessage(),
                'vendor' => "{$vendorType}:{$vendorId}",
            ]);
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function upsertRows(string $vendorType, int $vendorId, array $rows): void
    {
        $now = now();

        foreach ($rows as $row) {
            $sermsId = isset($row['reimbursement_id']) ? (int) $row['reimbursement_id'] : 0;
            if ($sermsId <= 0) {
                continue;
            }

            VendorReimbursement::updateOrCreate(
                [
                    'vendor_type'            => $vendorType,
                    'vendor_id'              => $vendorId,
                    'serms_reimbursement_id' => $sermsId,
                ],
                [
                    'reference_no'     => (string) ($row['reference_no'] ?? "RMB-{$sermsId}"),
                    'tin_number'       => isset($row['tin_number']) ? (string) $row['tin_number'] : null,
                    'vendor_name'      => (string) ($row['vendor_name'] ?? ''),
                    'category'         => isset($row['category']) ? (string) $row['category'] : null,
                    'description'      => isset($row['description']) ? (string) $row['description'] : null,
                    'amount'           => (float) ($row['amount'] ?? 0),
                    'status'           => (string) ($row['status'] ?? 'Pending'),
                    'employee_name'    => isset($row['employee_name']) ? (string) $row['employee_name'] : null,
                    'contract_id'      => isset($row['contract_id']) && $row['contract_id'] !== null ? (int) $row['contract_id'] : null,
                    'expense_date'     => isset($row['expense_date']) ? (string) $row['expense_date'] : null,
                    'serms_created_at' => isset($row['created_at']) ? Carbon::parse((string) $row['created_at']) : null,
                    'synced_at'        => $now,
                ]
            );
        }
    }
}
