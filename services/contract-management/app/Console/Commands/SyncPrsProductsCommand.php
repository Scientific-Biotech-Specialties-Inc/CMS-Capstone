<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Integrations\SyncPrsProductAction;
use Illuminate\Console\Command;

final class SyncPrsProductsCommand extends Command
{
    protected $signature = 'prs:sync-products {--since= : Optional ISO-8601 updated_since timestamp}';

    protected $description = 'Pull product catalog records from the configured PRS product_list API';

    public function handle(SyncPrsProductAction $action): int
    {
        $since = $this->option('since');
        $result = $action->pullFromPrs(is_string($since) ? $since : null);

        if (!empty($result['skipped'])) {
            $this->warn($result['reason'] ?? 'PRS product sync skipped cleanly (PRS unreachable).');
            return self::SUCCESS;
        }

        $this->info("Synced {$result['synced']} PRS product record(s) across {$result['pages']} page(s).");

        return self::SUCCESS;
    }
}
