<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Integrations\SyncPrsProductAction;
use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\IpUtils;

final class IntegrationProductController
{
    public function index(Request $request): JsonResponse
    {
        $networks = array_filter(array_map('trim', explode(',', (string) config('services.prs.catalog_networks'))));
        $secret = (string) config('services.prs.inbound_key');
        $hasServiceKey = $secret !== '' && hash_equals($secret, (string) $request->bearerToken());
        $isPrivatePeer = $networks !== [] && IpUtils::checkIp((string) $request->server('REMOTE_ADDR'), array_values($networks));
        abort_unless(config('services.prs.catalog_public_read') === true || $hasServiceKey || $isPrivatePeer, 403);

        $request->validate(['per_page' => 'sometimes|integer|min:1|max:100']);
        $products = Product::query()->orderBy('id')->cursorPaginate($request->integer('per_page', 50));

        return response()->json([
            'data'        => $products->getCollection()->map(fn (Product $product): array => $product->catalogDetails()),
            'next_cursor' => $products->nextCursor()?->encode(),
        ]);
    }

    /**
     * Authenticated CMS frontend product picker endpoint (`GET /api/products`).
     */
    public function listForUi(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $query = Product::query()->where('is_active', true)->orderBy('product_name');

        if ($search !== '') {
            $term = '%' . mb_substr($search, 0, 100) . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('product_name', 'like', $term)
                    ->orWhere('item_code', 'like', $term)
                    ->orWhere('serial_number', 'like', $term)
                    ->orWhere('brand', 'like', $term)
                    ->orWhere('model', 'like', $term);
            });
        }

        $products = $query->limit(500)->get();

        return response()->json([
            'data' => $products->map(fn (Product $p): array => $p->toPrsProductPayload())->values(),
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        abort_unless($product->is_active, 404);

        return response()->json(['data' => $product->toPrsProductPayload()]);
    }

    public function store(Request $request, SyncPrsProductAction $syncAction): JsonResponse
    {
        $data = $this->validateProduct($request);
        $sbuInt = Product::parseSbuInt($data['sbu'] ?? $data['sbu_number'] ?? null);
        $data['sbu'] = $sbuInt;
        $data['sbu_number'] = Product::formatSbuNumber($data['sbu_number'] ?? $sbuInt);

        $code = $data['item_code'] ?? null;
        if ($code !== null) {
            try {
                $product = Product::create($data);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['item_code' => 'This item code is already assigned.']);
            }

            return response()->json(['data' => $product->refresh()->catalogDetails()], 201);
        }

        $assigned = array_fill_keys(Product::query()->pluck('item_code')->all(), true);
        for ($number = 1; $number <= 9999; $number++) {
            $code = sprintf('ITM-%04d', $number);
            if (isset($assigned[$code])) {
                continue;
            }
            try {
                $product = Product::create([...$data, 'item_code' => $code]);

                return response()->json(['data' => $product->refresh()->catalogDetails()], 201);
            } catch (UniqueConstraintViolationException) {
                // Another writer assigned this code. Try the next free number.
            }
        }

        throw ValidationException::withMessages(['item_code' => 'All ITM-#### codes are assigned.']);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $this->validateProduct($request, $product);
        if (array_key_exists('sbu', $data) || array_key_exists('sbu_number', $data)) {
            $sbuInt = Product::parseSbuInt($data['sbu'] ?? $data['sbu_number'] ?? $product->sbu);
            $data['sbu'] = $sbuInt;
            $data['sbu_number'] = Product::formatSbuNumber($data['sbu_number'] ?? $sbuInt);
        }

        try {
            $product->update($data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['item_code' => 'This item code is already assigned.']);
        }

        return response()->json(['data' => $product->refresh()->catalogDetails()]);
    }

    private function validateProduct(Request $request, ?Product $product = null): array
    {
        $presence = $product ? 'sometimes' : 'required';
        $unique = Rule::unique('products', 'item_code')->where(fn ($q) => $q->whereNull('serial_number'));
        if ($product) {
            $unique->ignore($product);
        }

        return $request->validate([
            'item_code'     => [$product ? 'sometimes' : 'nullable', $product ? 'required' : 'string', 'max:255', $unique],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'product_name'  => [$presence, 'required', 'string', 'max:255'],
            'sbu_number'    => [$request->filled('sbu') ? 'sometimes' : $presence, 'string', 'max:20'],
            'sbu'           => ['nullable', 'integer', 'min:0', 'max:255'],
            'description'   => [$presence, 'required', 'string', 'max:1000'],
            'category'      => [$presence, 'required', 'string', 'max:255'],
            'manufacturer'  => ['nullable', 'string', 'max:255'],
            'brand'         => ['nullable', 'string', 'max:255'],
            'model'         => ['nullable', 'string', 'max:255'],
            'is_active'     => ['sometimes', 'boolean'],
        ]);
    }
}
