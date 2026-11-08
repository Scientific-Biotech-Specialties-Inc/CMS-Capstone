<?php

// Deletes probe/test contracts by code (instances cascade). Usage:
//   docker compose exec contract-management php delete_contracts.php CTR-XXX [...]
require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach (array_slice($argv, 1) as $code) {
    $c = App\Models\Contract::where('contract_code', $code)->first();
    if (!$c) {
        echo "$code: not found\n";
        continue;
    }
    $id = $c->contract_id;
    $c->delete();
    echo "$code (db $id): deleted\n";
}
