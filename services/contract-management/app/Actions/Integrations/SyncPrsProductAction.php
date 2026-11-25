<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Models\Product;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SyncPrsProductAction
{
    /**
     * Upsert a single product record (from PRS `product_list` or CMS Admin)
     * with dynamic ITM-#### code generation when `product_code`/`item_code` is omitted.
     *
     * @param array<string, mixed> $input
     */
    public function upsert(array $input): Product
    {
        $prsProductId = isset($input['prs_product_id']) ? (int) $input['prs_product_id'] : (isset($input['id']) ? (int) $input['id'] : null);
        $serialNumber = isset($input['serial_number']) && trim((string) $input['serial_number']) !== ''
            ? trim((string) $input['serial_number'])
            : null;
        $rawCode = trim((string) ($input['product_code'] ?? $input['item_code'] ?? ''));

        $sbuInt = Product::parseSbuInt($input['sbu'] ?? $input['sbu_number'] ?? null);
        $sbuNumber = Product::formatSbuNumber($input['sbu_number'] ?? $sbuInt);

        return DB::transaction(function () use ($input, $prsProductId, $serialNumber, $rawCode, $sbuInt, $sbuNumber): Product {
            $existing = null;
            if ($prsProductId !== null && $prsProductId > 0) {
                $existing = Product::where('prs_product_id', $prsProductId)->first();
            }
            if (!$existing && $rawCode !== '' && $serialNumber !== null) {
                $existing = Product::where('item_code', $rawCode)
                    ->where('serial_number', $serialNumber)
                    ->first();
            }
            if (!$existing && $rawCode !== '' && $serialNumber === null) {
                $existing = Product::where('item_code', $rawCode)
                    ->whereNull('serial_number')
                    ->first();
            }

            $itemCode = $rawCode !== '' ? $rawCode : ($existing?->item_code ?? $this->generateNextItemCode());

            $attributes = [
                'prs_product_id' => $prsProductId ?: $existing?->prs_product_id,
                'item_code'      => $itemCode,
                'serial_number'  => $serialNumber ?? $existing?->serial_number,
                'product_name'   => (string) ($input['product_name'] ?? $existing?->product_name ?? ''),
                'sbu_number'     => $sbuNumber,
                'sbu'            => $sbuInt ?? $existing?->sbu,
                'description'    => array_key_exists('description', $input) ? ($input['description'] !== null ? (string) $input['description'] : null) : $existing?->description,
                'category'       => array_key_exists('category', $input) ? ($input['category'] !== null ? (string) $input['category'] : 'Equipment') : ($existing?->category ?? 'Equipment'),
                'manufacturer'   => $input['manufacturer'] ?? $input['brand'] ?? $existing?->manufacturer,
                'brand'          => $input['brand'] ?? $input['manufacturer'] ?? $existing?->brand,
                'model'          => $input['model'] ?? $existing?->model,
                'is_active'      => array_key_exists('is_active', $input) ? (bool) $input['is_active'] : ($existing?->is_active ?? true),
            ];

            if ($existing) {
                $existing->update($attributes);
                return $existing->refresh();
            }

            return Product::create($attributes)->refresh();
        });
    }

    /**
     * Pull products from PRS `product_list` endpoint using configured base URL and key.
     * Degrades gracefully (without throwing) when PRS is unconfigured, offline, or its tunnel expired.
     *
     * @return array{synced: int, pages: int, skipped?: bool, reason?: string}
     */
    public function pullFromPrs(?string $updatedSince = null): array
    {
        $productsUrl = trim((string) config('services.prs.products_url', ''));
        if ($productsUrl === '') {
            $baseUrl = trim((string) config('services.prs.base_url', ''));
            if ($baseUrl === '') {
                $reason = 'PRS_PRODUCTS_URL or PRS_BASE_URL is not configured.';
                Log::info('SyncPrsProductAction::pullFromPrs skipped: ' . $reason);
                return ['synced' => 0, 'pages' => 0, 'skipped' => true, 'reason' => $reason];
            }
            $productsUrl = rtrim($baseUrl, '/') . '/api/integrations/cms/products';
        }

        $token = (string) (config('services.prs.outbound_key') ?: config('services.prs.inbound_key') ?: '');
        $headers = [
            'Accept'                      => 'application/json',
            'ngrok-skip-browser-warning'  => 'true',
        ];
        if ($token !== '') {
            $headers['Authorization'] = "Bearer {$token}";
            $headers['X-Integration-Secret'] = $token;
        }

        $synced = 0;
        $pages = 0;
        $cursor = null;
        $page = 1;

        try {
            do {
                $query = ['per_page' => 100];
                if ($updatedSince !== null && $updatedSince !== '') {
                    $query['updated_since'] = $updatedSince;
                }
                if ($cursor !== null) {
                    $query['cursor'] = $cursor;
                } else {
                    $query['page'] = $page;
                }

                $response = Http::connectTimeout(5)->timeout(15)->withHeaders($headers)->get($productsUrl, $query);

                if ($response->failed()) {
                    $reason = sprintf('PRS product catalog unreachable (HTTP %d — tunnel may have expired).', $response->status());
                    Log::warning('SyncPrsProductAction::pullFromPrs skipped: ' . $reason, ['url' => $productsUrl]);
                    return ['synced' => $synced, 'pages' => $pages, 'skipped' => true, 'reason' => $reason];
                }

                $body = $response->json();
                if (!is_array($body)) {
                    $reason = 'PRS product catalog returned a non-JSON response (tunnel may have expired).';
                    Log::warning('SyncPrsProductAction::pullFromPrs skipped: ' . $reason, ['url' => $productsUrl]);
                    return ['synced' => $synced, 'pages' => $pages, 'skipped' => true, 'reason' => $reason];
                }

                $rows = is_array($body['data'] ?? null) ? $body['data'] : (array_is_list($body) ? $body : []);

                foreach ($rows as $row) {
                    if (is_array($row) && !empty($row['product_name'])) {
                        $this->upsert($row);
                        $synced++;
                    }
                }

                $pages++;
                $cursor = $body['next_cursor'] ?? null;
                $lastPage = (int) ($body['meta']['last_page'] ?? $body['last_page'] ?? 1);
                $hasNextPage = $cursor !== null || ($page < $lastPage && !empty($rows));
                $page++;
            } while ($hasNextPage && $pages < 50);
        } catch (ConnectionException $e) {
            $reason = 'PRS unreachable (connection failed or tunnel expired): ' . $e->getMessage();
            Log::warning('SyncPrsProductAction::pullFromPrs skipped: ' . $reason, ['url' => $productsUrl]);
            return ['synced' => $synced, 'pages' => $pages, 'skipped' => true, 'reason' => $reason];
        } catch (Throwable $e) {
            $reason = 'PRS product pull error: ' . $e->getMessage();
            Log::warning('SyncPrsProductAction::pullFromPrs skipped: ' . $reason, ['url' => $productsUrl]);
            return ['synced' => $synced, 'pages' => $pages, 'skipped' => true, 'reason' => $reason];
        }

        return ['synced' => $synced, 'pages' => $pages, 'skipped' => false];
    }

    public function generateNextItemCode(): string
    {
        $assigned = array_fill_keys(Product::query()->pluck('item_code')->all(), true);
        for ($number = 1; $number <= 9999; $number++) {
            $code = sprintf('ITM-%04d', $number);
            if (!isset($assigned[$code])) {
                return $code;
            }
        }

        throw ValidationException::withMessages(['item_code' => 'All ITM-#### codes are assigned.']);
    }
}
