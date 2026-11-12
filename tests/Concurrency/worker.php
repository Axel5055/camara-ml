<?php

/**
 * Child process used by ConcurrencyTest: boots the application, waits for a common start instant so several
 * workers hit the database at the same time, runs one inventory operation and prints a JSON result.
 *
 * Usage: php worker.php '<json job>'
 */

use App\Models\Branch;
use App\Models\Chamber;
use App\Models\User;
use App\Services\Inventory\RegisterEntry;
use App\Services\Inventory\RegisterExit;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$job = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);

while (microtime(true) < $job['start']) {
    usleep(500);
}

try {
    $user = User::query()->findOrFail($job['user_id']);

    $result = match ($job['type']) {
        'exit' => app(RegisterExit::class)->handle(
            $user,
            Chamber::query()->findOrFail($job['origin_id']),
            Branch::query()->findOrFail($job['dest_branch_id']),
            null,
            $job['requester'],
            $job['box_ids'],
        )->reference,
        'entry' => app(RegisterEntry::class)->handle($user, Chamber::query()->findOrFail($job['chamber_id']), [[
            'product_id' => $job['product_id'],
            'boxes_count' => $job['count'],
            'weight_per_box_kg' => '10',
            'price_per_kg_mxn' => '10',
        ]])->reference,
    };

    echo json_encode(['ok' => true, 'result' => $result]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e::class.': '.$e->getMessage()]);
}
