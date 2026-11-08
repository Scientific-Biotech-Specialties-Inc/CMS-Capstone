<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Internal client to vendor-management's X-Internal-Secret-gated endpoints
 * (Feature 3: Vendor AI Suggestions) — used for the one-time embedding
 * backfill reading existing suppliers/business_partners, and for creating a
 * real vendor row once a suggested candidate is accepted.
 */
class VendorManagementClient
{
    protected string $baseUrl;
    protected string $secret;

    public function __construct()
    {
        $this->baseUrl = env('VENDOR_SERVICE_URL', 'http://vendor-management:8000/api');
        $this->secret  = env('INTERNAL_SERVICE_SECRET', '');
    }

    /**
     * @return array{id: int, type: 'supplier'|'partner', name: string, industry: ?string, region: ?string}[]
     */
    public function listAllVendorsForEmbedding(): array
    {
        $vendors = [];

        foreach (['suppliers' => 'supplier', 'partners' => 'partner'] as $endpoint => $type) {
            $page = 1;
            do {
                try {
                    $response = Http::withHeaders([
                        'Accept'            => 'application/json',
                        'X-Internal-Secret' => $this->secret,
                    ])->get("{$this->baseUrl}/internal/{$endpoint}", ['per_page' => 100, 'page' => $page]);
                } catch (\Exception $e) {
                    Log::error('VendorManagementClient: listAllVendorsForEmbedding connection error', [
                        'endpoint' => $endpoint,
                        'message'  => $e->getMessage(),
                    ]);
                    break;
                }

                if (!$response->successful()) {
                    Log::warning('VendorManagementClient: listAllVendorsForEmbedding failed', [
                        'endpoint' => $endpoint,
                        'status'   => $response->status(),
                    ]);
                    break;
                }

                $body = $response->json();
                $rows = $body['data'] ?? [];

                foreach ($rows as $row) {
                    $vendors[] = $type === 'supplier'
                        ? [
                            'id'       => $row['supplier_id'],
                            'type'     => 'supplier',
                            'name'     => $row['supplier_name'] ?? '',
                            'industry' => $row['industry'] ?? null,
                            'region'   => $row['region'] ?? null,
                        ]
                        : [
                            'id'       => $row['partner_id'],
                            'type'     => 'partner',
                            'name'     => $row['partner_name'] ?? '',
                            'industry' => $row['industry'] ?? null,
                            'region'   => $row['region'] ?? null,
                        ];
                }

                $hasMore = !empty($body['next_page_url'] ?? null);
                $page++;
            } while ($hasMore);
        }

        return $vendors;
    }

    /**
     * Creates a real supplier or business partner from an accepted
     * suggestion candidate. $type must be 'supplier' or 'partner'.
     *
     * @return array{ok: bool, message?: string, id?: int}
     */
    public function createVendor(string $type, array $fields, ?int $decidedBy): array
    {
        $endpoint = $type === 'supplier' ? 'suppliers' : 'partners';
        $payload = $type === 'supplier'
            ? [
                'supplier_name'  => $fields['name'],
                'tin_number'     => $fields['tin_number'] ?? null,
                'industry'       => $fields['industry'] ?? null,
                'contact_person' => $fields['contact_person'] ?? null,
                'contact_number' => $fields['phone'] ?? null,
                'email'          => $fields['email'] ?? null,
                'address'        => $fields['address'] ?? null,
                'region'         => $fields['region'] ?? null,
                'status'         => 'Active',
                'decided_by'     => $decidedBy,
            ]
            : [
                'bp_code'        => $fields['bp_code'] ?? ('BP-AI-' . time()),
                'partner_name'   => $fields['name'],
                'industry'       => $fields['industry'] ?? null,
                'contact_person' => $fields['contact_person'] ?? null,
                'contact_number' => $fields['phone'] ?? null,
                'email'          => $fields['email'] ?? '',
                'address'        => $fields['address'] ?? null,
                'region'         => $fields['region'] ?? null,
                'status'         => 'Active',
                'decided_by'     => $decidedBy,
            ];

        try {
            $response = Http::withHeaders([
                'Accept'            => 'application/json',
                'X-Internal-Secret' => $this->secret,
            ])->post("{$this->baseUrl}/internal/{$endpoint}", $payload);

            if (!$response->successful()) {
                return [
                    'ok'      => false,
                    'message' => $response->json('message') ?? 'Failed to create vendor record.',
                ];
            }

            $idKey = $type === 'supplier' ? 'supplier_id' : 'partner_id';

            return ['ok' => true, 'id' => $response->json("data.{$idKey}")];
        } catch (\Exception $e) {
            Log::error('VendorManagementClient: createVendor connection error', [
                'type'    => $type,
                'message' => $e->getMessage(),
            ]);
            return ['ok' => false, 'message' => 'Could not reach vendor-management.'];
        }
    }
}
