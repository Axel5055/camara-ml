<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * @return array<string, int>
 */
function seedInventoryBase(): array
{
    $now = now();
    $userId = User::factory()->create()->id;

    $branchId = DB::table('branches')->insertGetId(['code' => 'CEN', 'name' => 'Centro', 'created_at' => $now, 'updated_at' => $now]);
    $chamberId = DB::table('chambers')->insertGetId(['branch_id' => $branchId, 'code' => 'CEN-1', 'name' => 'Camara 1', 'created_at' => $now, 'updated_at' => $now]);
    $categoryId = DB::table('categories')->insertGetId(['name' => 'Aves', 'created_at' => $now, 'updated_at' => $now]);
    $productId = DB::table('products')->insertGetId(['category_id' => $categoryId, 'code' => 'POLLO', 'name' => 'Pollo', 'created_at' => $now, 'updated_at' => $now]);
    $entryId = DB::table('entries')->insertGetId([
        'reference' => 'ENT-000001', 'chamber_id' => $chamberId, 'user_id' => $userId, 'occurred_at' => $now,
        'total_boxes' => 1, 'total_weight_kg' => '20.000', 'total_cost_mxn' => '500.00', 'client_token' => 'tok-1',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $itemId = DB::table('entry_items')->insertGetId([
        'entry_id' => $entryId, 'product_id' => $productId, 'boxes_count' => 1, 'weight_per_box_kg' => '20.000',
        'unit_cost_mxn' => '500.00', 'total_weight_kg' => '20.000', 'total_cost_mxn' => '500.00',
        'created_at' => $now, 'updated_at' => $now,
    ]);

    return compact('userId', 'branchId', 'chamberId', 'categoryId', 'productId', 'entryId', 'itemId');
}

/**
 * @param  array<string, int>  $base
 * @param  array<string, mixed>  $overrides
 */
function insertBox(array $base, array $overrides = []): int
{
    return DB::table('boxes')->insertGetId(array_merge([
        'barcode' => 'POLLO-20261028-000001',
        'product_id' => $base['productId'],
        'category_id' => $base['categoryId'],
        'entry_item_id' => $base['itemId'],
        'origin_chamber_id' => $base['chamberId'],
        'initial_weight_kg' => '20.000',
        'current_weight_kg' => '20.000',
        'unit_cost_mxn' => '500.00',
        'entered_at' => now(),
        'status' => 'available',
        'current_chamber_id' => $base['chamberId'],
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

/**
 * @param  array<string, int>  $base
 */
function insertExit(array $base, string $reference = 'SAL-000001'): int
{
    return DB::table('exits')->insertGetId([
        'reference' => $reference, 'requester_name' => 'Juan', 'origin_chamber_id' => $base['chamberId'],
        'dest_branch_id' => $base['branchId'], 'user_id' => $base['userId'], 'occurred_at' => now(),
        'total_boxes' => 1, 'total_weight_kg' => '20.000', 'total_cost_mxn' => '500.00',
        'client_token' => $reference, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function insertExitItem(int $exitId, int $boxId, array $overrides = []): int
{
    return DB::table('exit_items')->insertGetId(array_merge([
        'exit_id' => $exitId, 'box_id' => $boxId, 'kind' => 'full', 'weight_before_kg' => '20.000',
        'weight_out_kg' => '20.000', 'weight_after_kg' => '0.000', 'unit_cost_snapshot_mxn' => '500.00',
        'cost_out_mxn' => '500.00', 'created_at' => now(), 'updated_at' => now(),
    ], $overrides));
}

it('rejects duplicate barcodes', function () {
    $base = seedInventoryBase();
    insertBox($base);

    expect(fn () => insertBox($base))->toThrow(QueryException::class);
});

it('rejects a box whose current weight exceeds its initial weight', function () {
    $base = seedInventoryBase();

    expect(fn () => insertBox($base, ['current_weight_kg' => '20.001']))->toThrow(QueryException::class);
});

it('rejects negative weights', function () {
    $base = seedInventoryBase();

    expect(fn () => insertBox($base, ['current_weight_kg' => '-1.000']))->toThrow(QueryException::class);
});

it('rejects an available box without a chamber and a dispatched box with one', function () {
    $base = seedInventoryBase();

    expect(fn () => insertBox($base, ['barcode' => 'POLLO-20261028-000002', 'current_chamber_id' => null]))
        ->toThrow(QueryException::class)
        ->and(fn () => insertBox($base, ['barcode' => 'POLLO-20261028-000003', 'status' => 'dispatched']))
        ->toThrow(QueryException::class);
});

it('rejects a depleted box that still has weight', function () {
    $base = seedInventoryBase();

    expect(fn () => insertBox($base, ['status' => 'depleted', 'current_chamber_id' => null]))
        ->toThrow(QueryException::class);
});

it('requires an origin for manual barcodes', function () {
    $base = seedInventoryBase();

    expect(fn () => insertBox($base, ['barcode' => 'ETIQ-99', 'barcode_source' => 'manual']))
        ->toThrow(QueryException::class);

    expect(insertBox($base, ['barcode' => 'ETIQ-99', 'barcode_source' => 'manual', 'manual_barcode_origin' => 'Proveedor X']))
        ->toBeInt();
});

it('rejects invalid product codes', function () {
    $base = seedInventoryBase();

    expect(fn () => DB::table('products')->insert([
        'category_id' => $base['categoryId'], 'code' => 'pollo-1', 'name' => 'Malo',
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('prevents a full box from being in two active exits but allows it after a reversal', function () {
    $base = seedInventoryBase();
    $boxId = insertBox($base);
    $first = insertExit($base);
    $second = insertExit($base, 'SAL-000002');

    $firstItem = insertExitItem($first, $boxId);

    expect(fn () => insertExitItem($second, $boxId))->toThrow(QueryException::class);

    DB::table('exit_items')->where('id', $firstItem)->update(['is_active' => false]);

    expect(insertExitItem($second, $boxId))->toBeInt();
});

it('allows several partial withdrawals of the same box', function () {
    $base = seedInventoryBase();
    $boxId = insertBox($base);

    $a = insertExitItem(insertExit($base), $boxId, [
        'kind' => 'partial', 'weight_out_kg' => '5.000', 'weight_after_kg' => '15.000',
    ]);
    $b = insertExitItem(insertExit($base, 'SAL-000002'), $boxId, [
        'kind' => 'partial', 'weight_before_kg' => '15.000', 'weight_out_kg' => '5.000', 'weight_after_kg' => '10.000',
    ]);

    expect([$a, $b])->each->toBeInt();
});

it('rejects exit items whose weights do not add up', function () {
    $base = seedInventoryBase();

    expect(fn () => insertExitItem(insertExit($base), insertBox($base), [
        'kind' => 'partial', 'weight_out_kg' => '5.000', 'weight_after_kg' => '10.000',
    ]))->toThrow(QueryException::class);
});

it('does not allow movements to be updated or deleted', function () {
    $base = seedInventoryBase();
    $boxId = insertBox($base);
    $movementId = DB::table('box_movements')->insertGetId([
        'box_id' => $boxId, 'type' => 'entry', 'to_chamber_id' => $base['chamberId'],
        'weight_before_kg' => '0.000', 'weight_after_kg' => '20.000', 'status_after' => 'available',
        'user_id' => $base['userId'], 'occurred_at' => now(), 'created_at' => now(),
    ]);

    expect(fn () => DB::table('box_movements')->where('id', $movementId)->update(['notes' => 'x']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('box_movements')->where('id', $movementId)->delete())
        ->toThrow(QueryException::class);
});

it('does not allow confirmed documents to be deleted or have business columns edited', function () {
    $base = seedInventoryBase();
    $exitId = insertExit($base);
    insertBox($base);

    expect(fn () => DB::table('exits')->where('id', $exitId)->update(['reference' => 'SAL-999999']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('exits')->where('id', $exitId)->delete())
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('boxes')->delete())
        ->toThrow(QueryException::class);

    DB::table('exits')->where('id', $exitId)->update([
        'status' => 'reversed', 'reversed_at' => now(), 'reversed_by' => $base['userId'], 'reversed_reason' => 'Error',
    ]);

    expect(DB::table('exits')->where('id', $exitId)->value('status'))->toBe('reversed');
});

it('keeps box cost immutable in practice through decimal precision', function () {
    $base = seedInventoryBase();
    $boxId = insertBox($base, ['unit_cost_mxn' => '549.99']);

    expect(DB::table('boxes')->where('id', $boxId)->value('unit_cost_mxn'))->toBe('549.99');
});
