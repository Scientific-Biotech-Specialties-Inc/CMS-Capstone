<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Integrations\SyncPrsProductAction;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Throwable;

final class PrsIntegrationController extends Controller
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'status'      => 'ok',
            'service'     => 'contract-management',
            'integration' => 'PRS',
            'timestamp'   => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/integrations/v1/prs/products
     * Returns products in exact PRS `product_list` schema.
     */
    public function products(Request $request): JsonResponse
    {
        $request->validate([
            'per_page'         => 'sometimes|integer|min:1|max:100',
            'include_inactive' => 'sometimes',
            'sbu'              => 'sometimes|string|max:20',
            'updated_since'    => 'sometimes|string|max:64',
            'search'           => 'sometimes|string|max:100',
        ]);

        $includeInactive = filter_var($request->query('include_inactive', false), FILTER_VALIDATE_BOOLEAN);
        $query = Product::query()->orderBy('id');

        if (!$includeInactive) {
            $query->where('is_active', true);
        }

        if ($sbuRaw = trim((string) $request->query('sbu', ''))) {
            $sbuInt = Product::parseSbuInt($sbuRaw);
            if ($sbuInt !== null) {
                $query->where('sbu', $sbuInt);
            }
        }

        if ($updatedSince = trim((string) $request->query('updated_since', ''))) {
            try {
                $query->where('updated_at', '>=', Carbon::parse($updatedSince));
            } catch (Throwable) {
                // Ignore unparseable timestamp filters.
            }
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $term = "%{$search}%";
            $query->where(function ($q) use ($term): void {
                $q->where('product_name', 'like', $term)
                    ->orWhere('item_code', 'like', $term)
                    ->orWhere('serial_number', 'like', $term)
                    ->orWhere('brand', 'like', $term)
                    ->orWhere('model', 'like', $term);
            });
        }

        $perPage = max(1, min($request->integer('per_page', 50), 100));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (Product $product): array => $product->toPrsProductPayload())
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'synced_at'    => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/integrations/v1/prs/products/sync
     * Inbound single or batch product upsert from PRS `product_list`.
     */
    public function syncProducts(Request $request, SyncPrsProductAction $action): JsonResponse
    {
        $items = $request->has('products')
            ? (array) $request->input('products')
            : [$request->all()];

        if ($items === []) {
            return response()->json(['message' => 'No product records provided.'], 422);
        }

        $synced = [];
        $syncedPrsIds = [];

        foreach ($items as $index => $rawItem) {
            if (!is_array($rawItem)) {
                return response()->json(['message' => "Invalid product payload at index {$index}."], 422);
            }

            $validator = Validator::make($rawItem, [
                'id'             => 'nullable|integer|min:1',
                'prs_product_id' => 'nullable|integer|min:1',
                'product_name'   => 'required|string|max:255',
                'product_code'   => 'nullable|string|max:255',
                'item_code'      => 'nullable|string|max:255',
                'serial_number'  => 'nullable|string|max:255',
                'sbu'            => 'nullable|integer|min:0|max:255',
                'sbu_number'     => 'nullable|string|max:20',
                'category'       => 'nullable|string|max:255',
                'manufacturer'   => 'nullable|string|max:255',
                'brand'          => 'nullable|string|max:255',
                'model'          => 'nullable|string|max:255',
                'is_active'      => 'nullable|boolean',
                'description'    => 'nullable|string|max:2000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $product = $action->upsert($validator->validated());
            $synced[] = $product->toPrsProductPayload();
            if ($product->prs_product_id !== null) {
                $syncedPrsIds[] = (int) $product->prs_product_id;
            }
        }

        if ($request->boolean('replace') && $syncedPrsIds !== []) {
            Product::query()
                ->whereNotNull('prs_product_id')
                ->whereNotIn('prs_product_id', $syncedPrsIds)
                ->update(['is_active' => false]);
        }

        return response()->json([
            'data'  => count($synced) === 1 ? $synced[0] : $synced,
            'count' => count($synced),
        ], 200);
    }

    /**
     * POST /api/integrations/v1/prs/products/pull
     * Admin-triggered pull from PRS `product_list` API.
     */
    public function pullProducts(Request $request, SyncPrsProductAction $action): JsonResponse
    {
        $since = $request->input('updated_since');
        $result = $action->pullFromPrs(is_string($since) ? $since : null);

        return response()->json([
            'message' => !empty($result['skipped'])
                ? ($result['reason'] ?? 'PRS unreachable; kept existing synced products.')
                : 'PRS product sync completed.',
            'data'    => $result,
        ]);
    }

    /**
     * Proxy institutions feed to vendor-management so single-port forwarding (:8002) works.
     */
    public function institutions(Request $request): JsonResponse
    {
        return $this->proxyVendor('GET', '/internal/prs/institutions', $request->query());
    }

    public function locations(Request $request): JsonResponse
    {
        return $this->proxyVendor('GET', '/internal/prs/locations', $request->query());
    }

    public function syncInstitutions(Request $request): JsonResponse
    {
        return $this->proxyVendor('POST', '/internal/prs/institutions/sync', $request->all());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function proxyVendor(string $method, string $path, array $payload): JsonResponse
    {
        $baseUrl = (string) config('services.vendor.url', '');
        $secret = (string) config('services.vendor.secret', '');

        if ($baseUrl !== '' && $secret !== '') {
            try {
                $pending = Http::connectTimeout(3)->timeout(8)->withHeaders([
                    'Accept'             => 'application/json',
                    'X-Internal-Secret'  => $secret,
                    'X-Internal-Service' => 'contract-management',
                ]);

                $response = $method === 'GET'
                    ? $pending->get("{$baseUrl}{$path}", $payload)
                    : $pending->post("{$baseUrl}{$path}", $payload);

                return response()->json($response->json() ?? [], $response->status());
            } catch (Throwable) {
                // Fall through to local fallback for isolated PHPUnit runs.
            }
        }

        if ($method === 'GET' && $path === '/internal/prs/institutions' && Schema::hasTable('business_partners')) {
            $rows = DB::table('business_partners')->orderBy('partner_id')->get()->map(fn (object $r): array => [
                'id'               => (int) $r->partner_id,
                'institution_uid'  => $r->bp_code,
                'institution_name' => $r->partner_name,
                'class_tier'       => $r->class_tier ?? 'Class C',
                'is_active'        => ($r->status ?? 'Active') === 'Active',
                'region'           => $r->region,
            ])->values();

            return response()->json(['data' => $rows, 'meta' => ['source_of_truth' => 'CMS']]);
        }

        return response()->json(['message' => 'Vendor management service unavailable.'], 503);
    }
}
