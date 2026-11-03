<?php

use App\Enums\BoxStatus;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\RegisterEntry;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/*
 * These tests commit to the test database and run several real PHP processes at the same time, which is the only
 * way to exercise row locks, the unique indexes and the barcode sequence under true concurrency.
 */

/**
 * @param  list<array<string, mixed>>  $jobs
 * @return list<array{ok: bool, result?: string, error?: string}>
 */
function runConcurrently(array $jobs): array
{
    $start = microtime(true) + 6;

    $processes = array_map(function (array $job) use ($start): Process {
        $process = new Process(
            [PHP_BINARY, __DIR__.'/worker.php', json_encode([...$job, 'start' => $start], JSON_THROW_ON_ERROR)],
            dirname(__DIR__, 2),
            ['APP_ENV' => 'testing', 'DB_DATABASE' => 'camara_refrigeracion_test', 'DB_CONNECTION' => 'mysql'],
        );
        $process->setTimeout(120);
        $process->start();

        return $process;
    }, $jobs);

    return array_map(function (Process $process): array {
        $process->wait();
        $lines = array_filter(explode("\n", trim($process->getOutput())));

        return json_decode((string) end($lines), true) ?? ['ok' => false, 'error' => 'No output: '.$process->getErrorOutput()];
    }, $processes);
}

beforeEach(function () {
    $this->artisan('migrate:fresh', ['--force' => true]);
});

afterEach(function () {
    $this->artisan('migrate:fresh', ['--force' => true]);
});

it('lets exactly one of several simultaneous exits take the same boxes', function () {
    $centro = Branch::factory()->create();
    $sur = Branch::factory()->create();
    $chamber = Chamber::factory()->for($centro)->create();
    $user = User::factory()->warehouse($centro)->create();
    $user->chambers()->attach($chamber);
    $product = Product::factory()->create();

    app(RegisterEntry::class)->handle($user, $chamber, [
        ['product_id' => $product->id, 'boxes_count' => 20, 'weight_per_box_kg' => '10', 'unit_cost_mxn' => '100'],
    ]);
    $ids = Box::query()->orderBy('id')->pluck('id')->all();

    // Same boxes, but each worker lists them in a different order: ordered locking must prevent deadlocks.
    $jobs = [];
    foreach ([$ids, array_reverse($ids), $ids, array_reverse($ids), $ids, array_reverse($ids)] as $order) {
        $jobs[] = ['type' => 'exit', 'user_id' => $user->id, 'origin_id' => $chamber->id, 'dest_branch_id' => $sur->id, 'requester' => 'Ana', 'box_ids' => $order];
    }

    $results = runConcurrently($jobs);
    $successes = array_filter($results, fn (array $r): bool => $r['ok']);

    expect($successes)->toHaveCount(1)
        ->and(DB::table('exits')->count())->toBe(1)
        ->and(DB::table('exit_items')->count())->toBe(20)
        ->and(Box::query()->where('status', BoxStatus::Dispatched)->count())->toBe(20);

    foreach ($results as $result) {
        if (! $result['ok']) {
            expect($result['error'])->toContain('InventoryException')->not->toContain('Deadlock');
        }
    }
});

it('never duplicates barcodes when several entries are registered at once', function () {
    $centro = Branch::factory()->create();
    $chamber = Chamber::factory()->for($centro)->create();
    $user = User::factory()->warehouse($centro)->create();
    $user->chambers()->attach($chamber);
    $product = Product::factory()->create();

    $jobs = array_fill(0, 5, ['type' => 'entry', 'user_id' => $user->id, 'chamber_id' => $chamber->id, 'product_id' => $product->id, 'count' => 40]);

    $results = runConcurrently($jobs);

    expect(array_filter($results, fn (array $r): bool => ! $r['ok']))->toBeEmpty()
        ->and(Box::query()->count())->toBe(200)
        ->and(Box::query()->distinct()->count('barcode'))->toBe(200)
        ->and(DB::table('entries')->distinct()->count('reference'))->toBe(5)
        ->and((int) DB::table('sequences')->where('name', 'barcode')->value('last_value'))->toBe(200);
});
