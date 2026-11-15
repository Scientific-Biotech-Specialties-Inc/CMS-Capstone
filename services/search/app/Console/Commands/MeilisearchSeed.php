<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MeilisearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Encryption\Encrypter;
use Carbon\Carbon;

final class MeilisearchSeed extends Command
{
    protected $signature = 'meilisearch:seed {--guard}';
    protected $description = 'Seed Meilisearch contracts index from MySQL';

    public function handle(MeilisearchService $meilisearch): int
    {
        $guard = $this->option('guard');

        if ($guard && !$meilisearch->indexIsEmpty()) {
            $this->info('Meilisearch contracts index is not empty. Skipping seeding.');
            return Command::SUCCESS;
        }

        $this->info('Seeding contracts to Meilisearch...');

        $key = config('app.field_encryption_key', env('FIELD_ENCRYPTION_KEY'));
        $encrypter = null;
        if ($key) {
            try {
                $encrypter = new Encrypter(base64_decode($key), 'aes-256-cbc');
            } catch (\Exception $e) {
                $this->error('Failed to initialize encrypter: ' . $e->getMessage());
            }
        }

        $decrypt = static function (?string $value) use ($encrypter): ?string {
            if ($value === null || $value === '' || !$encrypter) {
                return $value;
            }
            try {
                return $encrypter->decryptString($value);
            } catch (\Exception) {
                return $value;
            }
        };

        $contracts = DB::table('contracts')
            ->leftJoin('contract_categories', 'contracts.category_id', '=', 'contract_categories.category_id')
            ->leftJoin('contract_approval_statuses', 'contracts.approval_status_id', '=', 'contract_approval_statuses.approval_status_id')
            ->leftJoin('contract_statuses', 'contracts.workflow_status_id', '=', 'contract_statuses.status_id')
            ->leftJoin('contract_regions', 'contracts.region_id', '=', 'contract_regions.region_id')
            ->select([
                'contracts.contract_id',
                'contracts.contract_code',
                'contracts.execution_required',
                'contracts.signed_document_id',
                'contracts.bp_name',
                'contract_categories.category_name as category',
                'contract_approval_statuses.status_name as approval_status',
                'contract_statuses.status_name as workflow_status',
                'contracts.item_code',
                'contracts.description',
                'contracts.serial_number',
                'contracts.sbu_number',
                'contract_regions.region_name as region',
                'contracts.start_date',
                'contracts.end_date',
                'contracts.created_by',
            ])
            ->get();

        if ($contracts->isEmpty()) {
            $this->info('No contracts found to seed.');
            return Command::SUCCESS;
        }

        $documents = [];
        foreach ($contracts as $c) {
            $startDate = $c->start_date ? Carbon::parse($c->start_date)->toDateString() : null;
            $endDate = $c->end_date ? Carbon::parse($c->end_date)->toDateString() : null;

            $lifecycleStatus = match (true) {
                $c->approval_status !== null && $c->approval_status !== 'Approved' => 'inactive',
                $c->execution_required && !$c->signed_document_id => 'pending_execution',
                default => 'active',
            };
            if ($lifecycleStatus === 'active' && $endDate) {
                $days = (int) Carbon::today()->diffInDays(Carbon::parse($endDate), false);
                $lifecycleStatus = match (true) {
                    $days < 0 => 'expired',
                    $days <= 30 => 'expiring',
                    default => 'active',
                };
            }

            $documents[] = [
                'id'              => (int) $c->contract_id,
                'contract_id'     => $c->contract_code ?? (string) $c->contract_id,
                'contract_db_id'  => (int) $c->contract_id,
                'contract_code'   => $c->contract_code,
                'bp_name'         => $decrypt($c->bp_name),
                'category'        => $c->category,
                'approval_status' => $c->approval_status,
                'workflow_status' => $c->workflow_status,
                'item_code'       => $c->item_code,
                'description'     => $decrypt($c->description),
                'serial_number'   => $c->serial_number,
                'sbu_number'      => $c->sbu_number,
                'region'          => $c->region,
                'start_date'       => $startDate,
                'end_date'         => $endDate,
                'lifecycle_status' => $lifecycleStatus,
                'created_by'       => $c->created_by,
                'documents'        => [],
            ];
        }

        $meilisearch->createIndexIfMissing();
        $meilisearch->indexDocuments($documents);

        $this->info('Successfully indexed ' . count($documents) . ' contracts.');

        return Command::SUCCESS;
    }
}
