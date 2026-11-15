<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BusinessPartner;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VendorEmailSeeder extends Seeder
{
    /**
     * Backfill missing vendor emails with deterministic @example.com addresses
     * via Eloquent models so EncryptedCast encrypts values with the service key.
     */
    public function run(): void
    {
        $used = [];

        BusinessPartner::query()->orderBy('partner_id')->each(function (BusinessPartner $partner) use (&$used): void {
            $current = trim((string) $partner->email);
            if ($current !== '') {
                $used[strtolower($current)] = true;
                return;
            }

            $email = $this->uniqueExampleEmail((string) $partner->partner_name, 'partner-' . $partner->partner_id, $used);
            $partner->update(['email' => $email]);
        });

        Supplier::query()->orderBy('supplier_id')->each(function (Supplier $supplier) use (&$used): void {
            $current = trim((string) $supplier->email);
            if ($current !== '') {
                $used[strtolower($current)] = true;
                return;
            }

            $email = $this->uniqueExampleEmail((string) $supplier->supplier_name, 'supplier-' . $supplier->supplier_id, $used);
            $supplier->update(['email' => $email]);
        });
    }

    /**
     * @param array<string, bool> $used
     */
    private function uniqueExampleEmail(string $name, string $fallbackSlug, array &$used): string
    {
        $base = Str::limit(Str::slug($name) ?: $fallbackSlug, 64, '');
        $candidate = "{$base}@example.com";
        $suffix = 2;

        while (isset($used[strtolower($candidate)])) {
            $candidate = "{$base}-{$suffix}@example.com";
            $suffix++;
        }

        $used[strtolower($candidate)] = true;

        return $candidate;
    }
}
