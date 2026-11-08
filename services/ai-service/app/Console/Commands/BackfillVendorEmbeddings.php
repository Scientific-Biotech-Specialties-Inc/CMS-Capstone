<?php

namespace App\Console\Commands;

use App\Models\Embedding;
use App\Services\Gemini\GeminiClient;
use App\Services\VendorManagementClient;
use Illuminate\Console\Command;

/**
 * Feature 3 (Vendor AI Suggestions): one-time backfill embedding existing
 * suppliers/business_partners under entity_type='vendor', so the suggestion
 * pipeline can retrieve "similar vendors we already work with" as grounding
 * context. Idempotent — skips vendors that already have an embedding row.
 * Re-run manually after adding new vendors (or wire to create/update events
 * later — not required for this feature's scope).
 */
class BackfillVendorEmbeddings extends Command
{
    protected $signature   = 'ai:backfill-vendor-embeddings';
    protected $description = 'Embed existing suppliers/business_partners (entity_type=vendor) for Vendor AI Suggestions retrieval.';

    public function handle(VendorManagementClient $vendorClient, GeminiClient $gemini): int
    {
        $vendors = $vendorClient->listAllVendorsForEmbedding();

        if (empty($vendors)) {
            $this->info('No vendors found to embed.');
            return Command::SUCCESS;
        }

        $embedded = 0;
        $skipped  = 0;

        foreach ($vendors as $vendor) {
            // entity_id is only unique within an entity_type + a vendor_type
            // discriminator would collide supplier_id=1 with partner_id=1,
            // so we encode the type into the embedded entity_id space by
            // checking existing rows against both id and a stored model_name
            // tag rather than adding a new column (keeps the original
            // candidate schema's entity_type/entity_id shape intact).
            $modelTag = "vendor:{$vendor['type']}:{$vendor['id']}";

            $exists = Embedding::where('entity_type', 'vendor')
                ->where('entity_id', $vendor['id'])
                ->where('model_name', $modelTag)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $text = trim("{$vendor['name']} {$vendor['industry']} {$vendor['region']}");
            $vector = $gemini->embed($text);

            if ($vector === null) {
                $this->warn("  Skipped (embedding failed): {$vendor['name']}");
                continue;
            }

            Embedding::create([
                'entity_type' => 'vendor',
                'entity_id'   => $vendor['id'],
                'embedding'   => '[' . implode(',', $vector) . ']',
                'model_name'  => $modelTag,
            ]);

            $embedded++;
        }

        $this->info("Done. {$embedded} vendor(s) embedded, {$skipped} already up to date.");
        return Command::SUCCESS;
    }
}
