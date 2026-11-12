<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

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
            'data' => $products->getCollection()->map(fn (Product $product) => $product->catalogDetails()),
            'next_cursor' => $products->nextCursor()?->encode(),
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        abort_unless($product->is_active, 404);

        return response()->json(['data' => $product->catalogDetails()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateProduct($request);
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
        $unique = Rule::unique('products', 'item_code');
        if ($product) {
            $unique->ignore($product);
        }

        return $request->validate([
            'item_code' => [$product ? 'sometimes' : 'nullable', $product ? 'required' : 'string', 'regex:/^ITM-[0-9]{4}$/D', $unique],
            'product_name' => [$presence, 'required', 'string', 'max:255'],
            'sbu_number' => [$presence, 'required', 'regex:/^SBU-[0-9]{3}$/D'],
            'description' => [$presence, 'required', 'string', 'max:1000'],
            'category' => [$presence, 'required', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
