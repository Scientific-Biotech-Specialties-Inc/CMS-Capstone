<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Services\EncryptionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TsIntegrationController extends Controller
{
    public function __construct(
        private readonly EncryptionService $encryption
    ) {}

    /**
     * Unified CMS -> TS sync payload containing equipment and customer per Business Partner contract.
     */
    public function sync(Request $request): JsonResponse
    {
        if ($denied = $this->authorizeRequest($request)) {
            return $denied;
        }

        $entries = $this->resolveBusinessPartnerContracts($request);
        [$page, $perPage, $total, $sliced] = $this->paginateCollection($entries, $request);

        $data = $sliced->map(function (array $entry): array {
            /** @var Contract $contract */
            $contract = $entry['contract'];
            $equipment = $this->formatEquipment($contract);
            $customer = $entry['customer'];

            return [
                'equipment_uid' => $equipment['equipment_uid'],
                'serial_number' => $equipment['serial_number'],
                'customer_uid' => $customer['customer_uid'],
                'contract_id' => $contract->contract_id,
                'contract_code' => $equipment['contract_code'],
                'approval_status' => $equipment['approval_status'],
                'lifecycle_status' => $equipment['lifecycle_status'],
                'start_date' => $equipment['start_date'],
                'end_date' => $equipment['end_date'],
                'sbsi_branch' => $equipment['sbsi_branch'],
                'equipment' => $equipment,
                'customer' => $customer,
                'updated_at' => $contract->updated_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
                'source_of_truth' => 'CMS',
                'synced_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Equipment synchronization feed for Business Partner contracts only.
     */
    public function equipment(Request $request): JsonResponse
    {
        if ($denied = $this->authorizeRequest($request)) {
            return $denied;
        }

        $entries = $this->resolveBusinessPartnerContracts($request);
        [$page, $perPage, $total, $sliced] = $this->paginateCollection($entries, $request);

        $data = $sliced->map(function (array $entry): array {
            $customer = $entry['customer'];

            return array_merge($this->formatEquipment($entry['contract']), [
                'customer_uid' => $customer['customer_uid'],
                'customer_name' => $customer['customer_name'],
            ]);
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
                'source_of_truth' => 'CMS',
                'synced_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Customer synchronization feed returning Business Partners with contracts only
     * (excludes suppliers and uncontracted vendors), with email, SBSI branch, and ordered items.
     */
    public function customers(Request $request): JsonResponse
    {
        if ($denied = $this->authorizeRequest($request)) {
            return $denied;
        }

        $entries = $this->resolveBusinessPartnerContracts($request);
        $customersByKey = [];

        foreach ($entries as $entry) {
            /** @var Contract $contract */
            $contract = $entry['contract'];
            $customer = $entry['customer'];
            $key = $this->resolveCustomerKey($customer);

            if (!isset($customersByKey[$key])) {
                $customersByKey[$key] = $this->initVendorCustomerRecord($customer);
            }

            $item = $this->formatEquipment($contract);
            $branch = $item['sbsi_branch'];

            if (empty($customersByKey[$key]['ordered_items'])) {
                $customersByKey[$key]['sbsi_branch'] = $branch;
            }
            if (!in_array($branch, $customersByKey[$key]['sbsi_branches'], true)) {
                $customersByKey[$key]['sbsi_branches'][] = $branch;
            }

            $customersByKey[$key]['contract_ids'][] = $contract->contract_id;
            $customersByKey[$key]['equipment_uids'][] = $item['equipment_uid'];
            if (!empty($item['serial_number']) && !in_array($item['serial_number'], $customersByKey[$key]['serial_numbers'], true)) {
                $customersByKey[$key]['serial_numbers'][] = $item['serial_number'];
            }
            $customersByKey[$key]['ordered_items'][] = $item;

            if (empty($customersByKey[$key]['region']) && !empty($item['region'])) {
                $customersByKey[$key]['region'] = $item['region'];
            }
            if (!empty($item['updated_at']) && (empty($customersByKey[$key]['updated_at']) || $item['updated_at'] > $customersByKey[$key]['updated_at'])) {
                $customersByKey[$key]['updated_at'] = $item['updated_at'];
            }
        }

        $allCustomers = collect(array_values($customersByKey));
        [$page, $perPage, $total, $sliced] = $this->paginateCollection($allCustomers, $request);

        return response()->json([
            'data' => $sliced,
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
                'source_of_truth' => 'CMS',
                'synced_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function authorizeRequest(Request $request): ?JsonResponse
    {
        if (config('services.ts.public_read') === true) {
            return null;
        }

        $providedSecret = trim((string) ($request->header('X-Internal-Secret') ?? $request->bearerToken() ?? ''));
        $candidates = array_filter([
            (string) config('services.ts.inbound_key', ''),
            (string) config('services.ts.internal_secret', ''),
        ], fn (string $secret): bool => $secret !== '');

        foreach ($candidates as $expected) {
            if ($providedSecret !== '' && hash_equals($expected, $providedSecret)) {
                return null;
            }
        }

        return response()->json([
            'message' => 'Forbidden. Invalid or missing TS integration secret.',
        ], 403);
    }

    private function buildFilteredQuery(Request $request): Builder
    {
        $includeExpired = filter_var($request->query('include_expired', false), FILTER_VALIDATE_BOOLEAN);

        $query = Contract::query()
            ->with(['category', 'approvalStatus', 'workflowStatus', 'region'])
            ->whereHas('approvalStatus', fn (Builder $q) => $q->where('status_name', 'Approved'))
            ->orderBy('contract_id');

        if (!$includeExpired) {
            $today = now()->toDateString();
            $query->where(function (Builder $q) use ($today): void {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            });
        }

        if ($branch = trim((string) $request->query('sbsi_branch', ''))) {
            $query->where('sbsi_branch', $branch);
        }

        if ($serial = trim((string) $request->query('serial_number', ''))) {
            $query->where('serial_number', $serial);
        }

        if ($updatedSince = trim((string) $request->query('updated_since', ''))) {
            try {
                $since = Carbon::parse($updatedSince);
                $query->where('updated_at', '>=', $since);
            } catch (Throwable) {
                // Ignore unparseable timestamp filters instead of crashing the sync call.
            }
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $term = "%{$search}%";
            $query->where(function (Builder $q) use ($term): void {
                $q->where('contract_code', 'like', $term)
                    ->orWhere('bp_name', 'like', $term)
                    ->orWhere('serial_number', 'like', $term)
                    ->orWhere('item_code', 'like', $term)
                    ->orWhere('equipment_name', 'like', $term)
                    ->orWhere('brand', 'like', $term)
                    ->orWhere('model', 'like', $term)
                    ->orWhere('sbsi_branch', 'like', $term);
            });
        }

        return $query;
    }

    private function resolvePerPage(Request $request): int
    {
        return max(1, min((int) $request->query('per_page', 50), 200));
    }

    /**
     * Format Spec 3.1 equipment attributes cleanly with canonical UIDs.
     *
     * @return array<string, mixed>
     */
    private function formatEquipment(Contract $contract): array
    {
        $snapshot = is_array($contract->product_snapshot) ? $contract->product_snapshot : [];

        return [
            'equipment_uid' => 'EQP-' . $contract->contract_id,
            'contract_id' => $contract->contract_id,
            'contract_code' => $contract->contract_code ?? ('CTR-' . str_pad((string) $contract->contract_id, 5, '0', STR_PAD_LEFT)),
            'equipment_name' => $contract->equipment_name ?? ($snapshot['name'] ?? null),
            'brand' => $contract->brand ?? ($snapshot['brand'] ?? null),
            'model' => $contract->model ?? ($snapshot['model'] ?? null),
            'serial_number' => $contract->serial_number,
            'item_code' => $contract->item_code,
            'sbu_number' => $contract->sbu_number,
            'description' => $contract->description,
            'category' => $contract->category?->category_name,
            'sbsi_branch' => $contract->sbsi_branch ?: 'Main Office',
            'region' => $contract->region?->region_name,
            'approval_status' => $contract->approvalStatus?->status_name,
            'lifecycle_status' => $contract->lifecycle_status,
            'start_date' => $contract->start_date?->toDateString(),
            'end_date' => $contract->end_date?->toDateString(),
            'updated_at' => $contract->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Resolve contracts that belong to a Business Partner (excluding suppliers & unmatched rows).
     *
     * @return \Illuminate\Support\Collection<int, array{contract: Contract, customer: array<string, mixed>}>
     */
    private function resolveBusinessPartnerContracts(Request $request): \Illuminate\Support\Collection
    {
        $contracts = $this->buildFilteredQuery($request)->get();
        $vendorMaps = $this->loadVendorMaps($contracts->pluck('contract_id')->all());

        return $contracts
            ->map(function (Contract $contract) use ($vendorMaps): ?array {
                $partner = $this->resolveBusinessPartner($contract, $vendorMaps);
                if ($partner === null) {
                    return null;
                }

                $branch = $contract->sbsi_branch ?: 'Main Office';

                return [
                    'contract' => $contract,
                    'customer' => $this->formatPartnerCustomer($partner, $branch, $contract->region?->region_name),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @template T
     * @param \Illuminate\Support\Collection<int, T> $items
     * @return array{0: int, 1: int, 2: int, 3: \Illuminate\Support\Collection<int, T>}
     */
    private function paginateCollection(\Illuminate\Support\Collection $items, Request $request): array
    {
        $perPage = $this->resolvePerPage($request);
        $page = max(1, (int) $request->query('page', 1));
        $total = $items->count();

        return [$page, $perPage, $total, $items->forPage($page, $perPage)->values()];
    }

    /**
     * Resolve a contract's Business Partner (never returns Suppliers).
     *
     * @param array<string, mixed> $vendorMaps
     * @return array<string, mixed>|null
     */
    private function resolveBusinessPartner(Contract $contract, array $vendorMaps): ?array
    {
        $contractId = (int) $contract->contract_id;
        $assoc = $vendorMaps['associations'][$contractId] ?? null;

        if ($assoc !== null) {
            if (!empty($assoc['supplier_id'])) {
                return null;
            }
            if (!empty($assoc['partner_id']) && isset($vendorMaps['partners_by_id'][$assoc['partner_id']])) {
                return $vendorMaps['partners_by_id'][$assoc['partner_id']];
            }

            return null;
        }

        if ($contract->supplier_id) {
            return null;
        }

        $normalizedName = $this->normalizeName($contract->bp_name);
        if ($normalizedName !== '' && isset($vendorMaps['partners_by_name'][$normalizedName])) {
            return $vendorMaps['partners_by_name'][$normalizedName];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $partner
     * @return array<string, mixed>
     */
    private function formatPartnerCustomer(array $partner, string $branch = 'Main Office', ?string $fallbackRegion = null): array
    {
        $id = (int) ($partner['partner_id'] ?? $partner['id'] ?? 0);
        $code = trim((string) ($partner['bp_code'] ?? ''));

        return [
            'customer_uid' => $code !== '' ? $code : ('BP-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT)),
            'customer_type' => 'business_partner',
            'customer_source_id' => $id ?: null,
            'customer_name' => $partner['partner_name'] ?? null,
            'customer_email' => $partner['email'] ?? null,
            'contact_number' => $partner['contact_number'] ?? null,
            'contact_person' => $partner['contact_person'] ?? null,
            'address' => $partner['address'] ?? null,
            'sbsi_branch' => $branch,
            'region' => $partner['region'] ?? $fallbackRegion,
            'status' => $partner['status'] ?? null,
            'updated_at' => $partner['updated_at'] ?? null,
            'source_of_truth' => 'CMS',
        ];
    }

    /**
     * @param array<string, mixed> $customer
     * @return array<string, mixed>
     */
    private function initVendorCustomerRecord(array $customer): array
    {
        return array_merge($customer, [
            'sbsi_branches' => [],
            'contract_ids' => [],
            'equipment_uids' => [],
            'serial_numbers' => [],
            'ordered_items' => [],
        ]);
    }

    /**
     * @param array<string, mixed> $customer
     */
    private function resolveCustomerKey(array $customer): string
    {
        $sourceId = $customer['customer_source_id'] ?? null;
        return 'bp:' . ($sourceId ?: ($customer['customer_uid'] ?? ''));
    }

    /**
     * Fetch decrypted Business Partner data from vendor-management internal API (cross-service key isolation),
     * with a safe DB fallback for isolated PHPUnit runs.
     *
     * @param array<int, int|string> $contractIds
     * @return array<string, array<int|string, array<string, mixed>>>
     */
    private function loadVendorMaps(array $contractIds): array
    {
        $partners = $this->fetchVendorRecords('/internal/partners');
        if ($partners === null) {
            $partners = $this->loadPartnersFromDatabaseFallback();
        }

        $partnersById = [];
        $partnersByName = [];
        foreach ($partners as $partner) {
            $id = (int) ($partner['partner_id'] ?? $partner['id'] ?? 0);
            if ($id > 0) {
                $partnersById[$id] = $partner;
            }
            $norm = $this->normalizeName((string) ($partner['partner_name'] ?? ''));
            if ($norm !== '') {
                $partnersByName[$norm] = $partner;
            }
        }

        $associations = [];
        if ($contractIds !== [] && Schema::hasTable('vendor_contract_associations')) {
            $rows = DB::table('vendor_contract_associations')
                ->whereIn('contract_id', $contractIds)
                ->get(['contract_id', 'vendor_type', 'vendor_id']);

            foreach ($rows as $row) {
                $type = strtolower((string) ($row->vendor_type ?? ''));
                $vendorId = $row->vendor_id ? (int) $row->vendor_id : null;

                $associations[(int) $row->contract_id] = [
                    'partner_id' => in_array($type, ['partner', 'business_partner'], true) ? $vendorId : null,
                    'supplier_id' => $type === 'supplier' ? $vendorId : null,
                ];
            }
        }

        return [
            'partners_by_id' => $partnersById,
            'partners_by_name' => $partnersByName,
            'associations' => $associations,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchVendorRecords(string $path): ?array
    {
        $baseUrl = (string) config('services.vendor.url', '');
        $secret = (string) config('services.vendor.secret', '');
        if ($baseUrl === '' || $secret === '') {
            return null;
        }

        try {
            $records = [];
            $page = 1;

            do {
                $response = Http::connectTimeout(3)->timeout(4)->withHeaders([
                    'Accept' => 'application/json',
                    'X-Internal-Secret' => $secret,
                    'X-Internal-Service' => 'contract-management',
                ])->get("{$baseUrl}{$path}", [
                    'per_page' => 200,
                    'page' => $page,
                ]);

                if (!$response->successful()) {
                    Log::warning('Vendor service internal fetch failed during TS sync', [
                        'path' => $path,
                        'status' => $response->status(),
                    ]);

                    return $page === 1 ? null : $records;
                }

                $payload = $response->json();
                $items = $payload['data'] ?? [];
                if (is_array($items)) {
                    array_push($records, ...$items);
                }

                $lastPage = (int) ($payload['last_page'] ?? 1);
                $page++;
            } while ($page <= $lastPage && $page <= 20);

            return $records;
        } catch (Throwable $e) {
            Log::warning('Vendor service unreachable during TS sync; using local fallback if present', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadPartnersFromDatabaseFallback(): array
    {
        if (!Schema::hasTable('business_partners')) {
            return [];
        }

        return DB::table('business_partners')
            ->get()
            ->map(fn (object $row): array => [
                'partner_id' => (int) ($row->partner_id ?? 0),
                'bp_code' => $row->bp_code ?? null,
                'partner_name' => $row->partner_name ?? null,
                'contact_person' => $this->safeDecrypt($row->contact_person ?? null),
                'email' => $this->safeDecrypt($row->email ?? null),
                'contact_number' => $this->safeDecrypt($row->contact_number ?? null),
                'address' => $this->safeDecrypt($row->address ?? null),
                'region' => $row->region ?? null,
                'status' => $row->status ?? null,
                'updated_at' => $row->updated_at ?? null,
            ])
            ->all();
    }

    private function safeDecrypt(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return $this->encryption->decrypt($value);
        } catch (Throwable) {
            // Different service key or plain seeded text in tests.
            return $this->isLikelyEncryptedPayload($value) ? null : $value;
        }
    }

    private function isLikelyEncryptedPayload(string $value): bool
    {
        $decoded = base64_decode($value, true);

        return $decoded !== false && strlen($decoded) >= 28 && preg_match('/^[A-Za-z0-9+\/=]+$/', $value) === 1;
    }

    private function normalizeName(?string $name): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $name) ?? ''));
    }
}
