<?php

use App\Enums\AdjustmentType;
use App\Enums\BoxStatus;
use App\Enums\DocumentStatus;
use App\Enums\IncidentType;
use App\Enums\MovementType;
use App\Enums\TransferItemStatus;
use App\Enums\TransferStatus;
use App\Exceptions\InventoryException;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\Entry;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\AdjustInventory;
use App\Services\Inventory\AdjustStockSummary;
use App\Services\Inventory\CancelTransfer;
use App\Services\Inventory\CorrectEntry;
use App\Services\Inventory\CorrectExit;
use App\Services\Inventory\EditBox;
use App\Services\Inventory\ReceiveTransfer;
use App\Services\Inventory\RegisterEntry;
use App\Services\Inventory\RegisterExit;
use App\Services\Inventory\ReverseExit;
use App\Services\Inventory\WithdrawPartial;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

afterEach(fn () => Carbon::setTestNow());

/**
 * Centro (chamber 1) and Norte (chambers 2 and 3), plus Sur without chambers.
 *
 * @return array<string, mixed>
 */
function warehouseWorld(): array
{
    $centro = Branch::factory()->create(['name' => 'Centro']);
    $norte = Branch::factory()->create(['name' => 'Norte']);
    $sur = Branch::factory()->create(['name' => 'Sur']);

    $c1 = Chamber::factory()->for($centro)->create();
    $n1 = Chamber::factory()->for($norte)->create();
    $n2 = Chamber::factory()->for($norte)->create();

    $admin = User::factory()->superAdmin()->create();
    $centroUser = User::factory()->warehouse($centro)->create();
    $centroUser->chambers()->attach($c1);
    $norteUser = User::factory()->warehouse($norte)->create();
    $norteUser->chambers()->attach([$n1->id, $n2->id]);

    $product = Product::factory()->create(['code' => 'POLLO', 'name' => 'Pollo entero']);

    return compact('centro', 'norte', 'sur', 'c1', 'n1', 'n2', 'admin', 'centroUser', 'norteUser', 'product');
}

/**
 * Register the example entry from the spec: 10×20kg@500, 15×22kg@550, 8×18kg@470.
 *
 * @param  array<string, mixed>  $w
 */
function registerExampleEntry(array $w): Entry
{
    return app(RegisterEntry::class)->handle($w['centroUser'], $w['c1'], [
        ['product_id' => $w['product']->id, 'boxes_count' => 10, 'weight_per_box_kg' => '20', 'price_per_kg_mxn' => '25.00'],
        ['product_id' => $w['product']->id, 'boxes_count' => 15, 'weight_per_box_kg' => '22', 'price_per_kg_mxn' => '25.00'],
        ['product_id' => $w['product']->id, 'boxes_count' => 8, 'weight_per_box_kg' => '18', 'price_per_kg_mxn' => '26.00'],
    ]);
}

it('registers an entry with several groups, exact totals and one unique barcode per box', function () {
    Carbon::setTestNow('2026-10-28 10:00:00');
    $w = warehouseWorld();

    $entry = registerExampleEntry($w);

    expect($entry->total_boxes)->toBe(33)
        ->and($entry->total_weight_kg)->toBe('674.000')
        ->and($entry->total_cost_mxn)->toBe('16994.00')
        ->and($entry->items)->toHaveCount(3);

    $barcodes = Box::query()->orderBy('id')->pluck('barcode');

    expect($barcodes)->toHaveCount(33)
        ->and($barcodes->first())->toBe('POL28LOE10NTE000001')
        ->and($barcodes->last())->toBe('POL28LOE10NTE000033')
        ->and($barcodes->unique())->toHaveCount(33)
        ->and(Box::query()->where('status', BoxStatus::Available)->where('current_chamber_id', $w['c1']->id)->count())->toBe(33)
        ->and(DB::table('box_movements')->where('type', MovementType::Entry->value)->count())->toBe(33);
});

it('keeps the barcode sequence global across days and grows past six digits', function () {
    $w = warehouseWorld();
    $group = [['product_id' => $w['product']->id, 'boxes_count' => 1, 'weight_per_box_kg' => '20', 'price_per_kg_mxn' => '25.00']];

    Carbon::setTestNow('2026-10-28 10:00:00');
    app(RegisterEntry::class)->handle($w['centroUser'], $w['c1'], $group);

    Carbon::setTestNow('2026-10-29 10:00:00');
    DB::table('sequences')->where('name', 'barcode')->update(['last_value' => 999999]);
    app(RegisterEntry::class)->handle($w['centroUser'], $w['c1'], $group);

    expect(Box::query()->orderBy('id')->pluck('barcode')->all())
        ->toBe(['POL28LOE10NTE000001', 'POL29LOE10NTE1000000']);
});

it('accepts manual barcodes with an origin and rejects reserved, duplicated or unexplained ones', function () {
    $w = warehouseWorld();
    $make = fn (array $extra) => app(RegisterEntry::class)->handle($w['centroUser'], $w['c1'], [
        array_merge(['product_id' => $w['product']->id, 'boxes_count' => 1, 'weight_per_box_kg' => '10', 'price_per_kg_mxn' => '10.00'], $extra),
    ]);

    $make(['manual_barcodes' => ['etiq-100'], 'manual_barcode_origin' => 'Proveedor X']);
    expect(Box::query()->first()->barcode)->toBe('ETIQ-100');

    expect(fn () => $make(['manual_barcodes' => ['ETIQ-100'], 'manual_barcode_origin' => 'Proveedor X']))->toThrow(InventoryException::class)
        ->and(fn () => $make(['manual_barcodes' => ['POLLO-20261028-000001'], 'manual_barcode_origin' => 'X']))->toThrow(InventoryException::class)
        ->and(fn () => $make(['manual_barcodes' => ['POL28LOE10NTE000001'], 'manual_barcode_origin' => 'X']))->toThrow(InventoryException::class)
        ->and(fn () => $make(['manual_barcodes' => ['ETIQ-200']]))->toThrow(InventoryException::class);

    expect(Box::query()->count())->toBe(1);
});

it('is idempotent for the same client token', function () {
    $w = warehouseWorld();
    $group = [['product_id' => $w['product']->id, 'boxes_count' => 2, 'weight_per_box_kg' => '10', 'price_per_kg_mxn' => '10.00']];

    $first = app(RegisterEntry::class)->handle($w['centroUser'], $w['c1'], $group, clientToken: 'same-token');
    $second = app(RegisterEntry::class)->handle($w['centroUser'], $w['c1'], $group, clientToken: 'same-token');

    expect($second->id)->toBe($first->id)->and(Box::query()->count())->toBe(2);
});

it('rolls back the whole entry when a product is inactive', function () {
    $w = warehouseWorld();
    $inactive = Product::factory()->create(['is_active' => false]);

    expect(fn () => app(RegisterEntry::class)->handle($w['centroUser'], $w['c1'], [
        ['product_id' => $w['product']->id, 'boxes_count' => 2, 'weight_per_box_kg' => '10', 'price_per_kg_mxn' => '10.00'],
        ['product_id' => $inactive->id, 'boxes_count' => 1, 'weight_per_box_kg' => '10', 'price_per_kg_mxn' => '10.00'],
    ]))->toThrow(InventoryException::class);

    expect(Box::query()->count())->toBe(0)->and(Entry::query()->count())->toBe(0);
});

it('rejects float-like precision, zero weight and negative cost', function () {
    $w = warehouseWorld();
    $try = fn (array $g) => fn () => app(RegisterEntry::class)->handle($w['centroUser'], $w['c1'], [
        array_merge(['product_id' => $w['product']->id, 'boxes_count' => 1, 'weight_per_box_kg' => '10', 'price_per_kg_mxn' => '10.00'], $g),
    ]);

    expect($try(['weight_per_box_kg' => '10.0005']))->toThrow(InventoryException::class)
        ->and($try(['price_per_kg_mxn' => '100.005']))->toThrow(InventoryException::class)
        ->and($try(['weight_per_box_kg' => '0']))->toThrow(InventoryException::class)
        ->and($try(['price_per_kg_mxn' => '-1']))->toThrow(InventoryException::class);
});

it('enforces chamber access on the server', function () {
    $w = warehouseWorld();
    $group = [['product_id' => $w['product']->id, 'boxes_count' => 1, 'weight_per_box_kg' => '10', 'price_per_kg_mxn' => '10.00']];

    expect(fn () => app(RegisterEntry::class)->handle($w['norteUser'], $w['c1'], $group))->toThrow(AuthorizationException::class);

    $inactive = User::factory()->superAdmin()->create(['is_active' => false]);
    expect(fn () => app(RegisterEntry::class)->handle($inactive, $w['c1'], $group))->toThrow(AuthorizationException::class);
});

it('exits exactly the selected boxes to a branch without chamber and keeps historic cost', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);

    // 11th and 12th boxes (22kg @ $550) and 26th (18kg @ $470) -> 62 kg / $1,570
    $all = Box::query()->orderBy('id')->pluck('id')->values();
    $ids = [$all[10], $all[11], $all[25]];

    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Juan Pérez', $ids);

    expect($exit->total_boxes)->toBe(3)
        ->and($exit->total_weight_kg)->toBe('62.000')
        ->and($exit->total_cost_mxn)->toBe('1568.00')
        ->and($exit->transfer)->toBeNull()
        ->and(Box::query()->whereIn('id', $ids)->pluck('status')->unique()->all())->toBe([BoxStatus::Dispatched])
        ->and(Box::query()->where('status', BoxStatus::Available)->count())->toBe(30);
});

it('cancels the whole exit when any selected box is unavailable', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $ids = Box::query()->orderBy('id')->limit(3)->pluck('id')->all();

    app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$ids[0]]);

    expect(fn () => app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Luis', $ids))
        ->toThrow(InventoryException::class);

    expect(Box::query()->whereIn('id', [$ids[1], $ids[2]])->where('status', BoxStatus::Available)->count())->toBe(2)
        ->and(DB::table('exits')->count())->toBe(1);
});

it('does not allow selecting boxes from another chamber or repeating a box', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $box = Box::query()->first();

    expect(fn () => app(RegisterExit::class)->handle($w['norteUser'], $w['n1'], $w['sur'], null, 'Ana', [$box->id]))
        ->toThrow(InventoryException::class)
        ->and(fn () => app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$box->id, $box->id]))
        ->toThrow(InventoryException::class)
        ->and(fn () => app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, '  ', [$box->id]))
        ->toThrow(InventoryException::class);
});

it('transfers boxes keeping barcode, weight and cost, and records missing and weight incidents', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $boxes = Box::query()->orderBy('id')->limit(3)->get();

    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n2'], 'Sucursal Norte', $boxes->pluck('id')->all());
    $transfer = $exit->transfer;

    expect($transfer->status)->toBe(TransferStatus::InTransit)
        ->and(Box::query()->whereIn('id', $boxes->pluck('id'))->where('status', BoxStatus::InTransit)->count())->toBe(3)
        ->and(Box::query()->where('status', BoxStatus::Available)->count())->toBe(30);

    // Only 2 arrive; one with a different weight; one is missing.
    $received = app(ReceiveTransfer::class)->handle(
        $w['norteUser'], $transfer, [$boxes[0]->id, $boxes[1]->id], [$boxes[1]->id => '19.500'],
    );

    expect($received->status)->toBe(TransferStatus::Received);

    $arrived = $boxes[0]->fresh();
    expect($arrived->status)->toBe(BoxStatus::Available)
        ->and($arrived->current_chamber_id)->toBe($w['n2']->id)
        ->and($arrived->barcode)->toBe($boxes[0]->barcode)
        ->and($arrived->unit_cost_mxn)->toBe($boxes[0]->unit_cost_mxn)
        ->and($boxes[2]->fresh()->status)->toBe(BoxStatus::InTransit)
        ->and($transfer->items()->where('status', TransferItemStatus::Missing)->count())->toBe(1)
        ->and($transfer->incidents()->pluck('type')->map->value->sort()->values()->all())
        ->toBe([IncidentType::Missing->value, IncidentType::WeightDifference->value])
        ->and(Box::query()->where('entry_item_id', '>', 0)->count())->toBe(33)
        ->and(DB::table('entries')->count())->toBe(1);

    expect(fn () => app(ReceiveTransfer::class)->handle($w['norteUser'], $transfer, [$boxes[2]->id]))
        ->toThrow(InventoryException::class);
});

it('only lets the destination chamber receive a transfer', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $box = Box::query()->first();
    $transfer = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Norte', [$box->id])->transfer;

    expect(fn () => app(ReceiveTransfer::class)->handle($w['centroUser'], $transfer, [$box->id]))
        ->toThrow(AuthorizationException::class);
});

it('lets only a super administrator cancel a transfer in transit, returning boxes to origin so they can leave again', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $box = Box::query()->first();
    $transfer = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Norte', [$box->id])->transfer;

    expect(fn () => app(CancelTransfer::class)->handle($w['centroUser'], $transfer, 'Error de destino'))
        ->toThrow(AuthorizationException::class);

    app(CancelTransfer::class)->handle($w['admin'], $transfer, 'Error de destino');

    expect($transfer->fresh()->status)->toBe(TransferStatus::Cancelled)
        ->and($transfer->exit->fresh()->status)->toBe(DocumentStatus::Reversed)
        ->and($box->fresh()->status)->toBe(BoxStatus::Available)
        ->and($box->fresh()->current_chamber_id)->toBe($w['c1']->id)
        ->and(DB::table('box_movements')->where('type', MovementType::TransferCancel->value)->count())->toBe(1);

    $again = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$box->id]);
    expect($again->total_boxes)->toBe(1);

    expect(fn () => app(CancelTransfer::class)->handle($w['admin'], $transfer->fresh(), 'Otra vez'))
        ->toThrow(InventoryException::class);
});

it('lets only the super administrator cancel a received transfer, sending the boxes back to the origin', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $boxes = Box::query()->orderBy('id')->limit(3)->get();
    $transfer = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Norte', $boxes->pluck('id')->all())->transfer;
    app(ReceiveTransfer::class)->handle($w['norteUser'], $transfer, [$boxes[0]->id, $boxes[1]->id]);

    expect($transfer->fresh()->incidents()->where('status', 'open')->count())->toBe(1);

    foreach (['norteUser', 'centroUser'] as $role) {
        expect(fn () => app(CancelTransfer::class)->handle($w[$role], $transfer->fresh(), 'Equivocación al recibir'))
            ->toThrow(AuthorizationException::class);
    }

    app(CancelTransfer::class)->handle($w['admin'], $transfer->fresh(), 'Equivocación al recibir');

    expect($transfer->fresh()->status)->toBe(TransferStatus::Cancelled)
        ->and($transfer->exit->fresh()->status)->toBe(DocumentStatus::Reversed)
        ->and(Box::query()->whereIn('id', $boxes->pluck('id'))->where('status', BoxStatus::Available)->where('current_chamber_id', $w['c1']->id)->count())->toBe(3)
        ->and($transfer->fresh()->incidents()->where('status', 'open')->count())->toBe(0)
        ->and(DB::table('box_movements')->where('type', MovementType::TransferCancel->value)->where('from_chamber_id', $w['n1']->id)->count())->toBe(2);

    $again = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$boxes[0]->id]);
    expect($again->total_boxes)->toBe(1);

    expect(fn () => app(CancelTransfer::class)->handle($w['admin'], $transfer->fresh(), 'Otra vez'))
        ->toThrow(InventoryException::class);
});

it('refuses to cancel a received transfer when a received box already left the destination', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $boxes = Box::query()->orderBy('id')->limit(2)->get();
    $transfer = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Norte', $boxes->pluck('id')->all())->transfer;
    app(ReceiveTransfer::class)->handle($w['norteUser'], $transfer, $boxes->pluck('id')->all());
    app(RegisterExit::class)->handle($w['norteUser'], $w['n1'], $w['sur'], null, 'Ana', [$boxes[0]->id]);

    expect(fn () => app(CancelTransfer::class)->handle($w['admin'], $transfer->fresh(), 'Equivocación al recibir'))
        ->toThrow(InventoryException::class, 'ya no está intacta');

    expect($transfer->fresh()->status)->toBe(TransferStatus::Received)
        ->and($boxes[1]->fresh()->current_chamber_id)->toBe($w['n1']->id);
});

it('lets only admins reverse a delivery and restores the box', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $box = Box::query()->first();
    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$box->id]);

    expect(fn () => app(ReverseExit::class)->handle($w['centroUser'], $exit, 'Me equivoqué'))
        ->toThrow(AuthorizationException::class);

    app(ReverseExit::class)->handle($w['admin'], $exit, 'Me equivoqué');

    $movement = $box->movements()->where('type', MovementType::Reversal->value)->first();

    expect($box->fresh()->status)->toBe(BoxStatus::Available)
        ->and($exit->fresh()->status)->toBe(DocumentStatus::Reversed)
        ->and($movement->reverses_movement_id)->not->toBeNull()
        ->and($box->movements()->count())->toBe(3)
        ->and(DB::table('audit_logs')->where('event', 'exit.reversed')->count())->toBe(1);

    expect(fn () => app(ReverseExit::class)->handle($w['admin'], $exit->fresh(), 'Otra vez'))->toThrow(InventoryException::class);
});

it('withdraws part of a box keeping its barcode and remaining weight until depleted', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $box = Box::query()->first(); // 20 kg @ $500

    $exit = app(WithdrawPartial::class)->handle($w['centroUser'], $box, '5', $w['sur'], 'Ana');

    expect($exit->total_weight_kg)->toBe('5.000')
        ->and($exit->total_cost_mxn)->toBe('125.00')
        ->and($box->fresh()->current_weight_kg)->toBe('15.000')
        ->and($box->fresh()->status)->toBe(BoxStatus::Available)
        ->and($box->fresh()->isPartial())->toBeTrue()
        ->and($box->fresh()->initial_weight_kg)->toBe('20.000');

    expect(fn () => app(WithdrawPartial::class)->handle($w['centroUser'], $box->fresh(), '15.001', $w['sur'], 'Ana'))
        ->toThrow(InventoryException::class);

    app(WithdrawPartial::class)->handle($w['centroUser'], $box->fresh(), '15', $w['sur'], 'Ana');

    expect($box->fresh()->status)->toBe(BoxStatus::Depleted)
        ->and($box->fresh()->current_chamber_id)->toBeNull()
        ->and($box->movements()->where('type', MovementType::PartialWithdrawal->value)->count())->toBe(2);
});

it('reverses partial withdrawals in order and refuses when later changes happened', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $box = Box::query()->first();

    $first = app(WithdrawPartial::class)->handle($w['centroUser'], $box, '5', $w['sur'], 'Ana');
    $second = app(WithdrawPartial::class)->handle($w['centroUser'], $box->fresh(), '3', $w['sur'], 'Ana');

    expect(fn () => app(ReverseExit::class)->handle($w['admin'], $first, 'Corregir primero'))->toThrow(InventoryException::class);

    app(ReverseExit::class)->handle($w['admin'], $second, 'Corregir segundo');
    app(ReverseExit::class)->handle($w['admin'], $first->fresh(), 'Corregir primero');

    expect($box->fresh()->current_weight_kg)->toBe('20.000');
});

it('applies admin-only adjustments with reason and full before/after trail', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $box = Box::query()->first();

    expect(fn () => app(AdjustInventory::class)->handle($w['centroUser'], $box, AdjustmentType::WeightCorrection, 'Báscula descalibrada', '19.000'))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(AdjustInventory::class)->handle($w['admin'], $box, AdjustmentType::WeightCorrection, 'x', '19.000'))
        ->toThrow(InventoryException::class)
        ->and(fn () => app(AdjustInventory::class)->handle($w['admin'], $box, AdjustmentType::WeightCorrection, 'Peso mayor al inicial', '21.000'))
        ->toThrow(InventoryException::class);

    $adjustment = app(AdjustInventory::class)->handle($w['admin'], $box, AdjustmentType::WeightCorrection, 'Báscula descalibrada', '19.000');

    expect($adjustment->weight_before_kg)->toBe('20.000')
        ->and($adjustment->weight_after_kg)->toBe('19.000')
        ->and($box->fresh()->current_weight_kg)->toBe('19.000');

    app(AdjustInventory::class)->handle($w['admin'], $box, AdjustmentType::Lost, 'Caja extraviada en cámara');

    expect($box->fresh()->status)->toBe(BoxStatus::WrittenOff)
        ->and($box->fresh()->current_chamber_id)->toBeNull();

    app(AdjustInventory::class)->handle($w['admin'], $box, AdjustmentType::Found, 'Se encontró en pasillo', chamber: $w['c1']);

    expect($box->fresh()->status)->toBe(BoxStatus::Available)
        ->and($box->movements()->where('type', MovementType::Adjustment->value)->count())->toBe(3)
        ->and(DB::table('audit_logs')->where('event', 'inventory.adjusted')->count())->toBe(3);
});

it('gives every box a complete ordered history', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $box = Box::query()->first();
    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Norte', [$box->id]);
    app(ReceiveTransfer::class)->handle($w['norteUser'], $exit->transfer, [$box->id]);

    expect($box->movements->pluck('type')->map->value->all())
        ->toBe(['entry', 'transfer_out', 'transfer_in']);
});

it('lets a box leave again after the destination received it by transfer', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $box = Box::query()->first();
    $transfer = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Norte', [$box->id])->transfer;

    app(ReceiveTransfer::class)->handle($w['norteUser'], $transfer, [$box->id]);

    expect(DB::table('exit_items')->where('box_id', $box->id)->value('holds_box'))->toBe(0)
        ->and(DB::table('exit_items')->where('box_id', $box->id)->value('is_active'))->toBe(1);

    $delivery = app(RegisterExit::class)->handle($w['norteUser'], $w['n1'], $w['sur'], null, 'Ana', [$box->id]);
    expect($delivery->total_boxes)->toBe(1)
        ->and($box->fresh()->status)->toBe(BoxStatus::Dispatched);

    $second = Box::query()->orderBy('id')->skip(1)->first();
    $again = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Norte', [$second->id])->transfer;
    app(ReceiveTransfer::class)->handle($w['norteUser'], $again, [$second->id]);
    $forwarded = app(RegisterExit::class)->handle($w['norteUser'], $w['n1'], $w['centro'], $w['c1'], 'Centro', [$second->id]);
    expect($forwarded->transfer->status)->toBe(TransferStatus::InTransit);
});

it('still keeps a box that is out from being in two standing exits', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $box = Box::query()->first();
    app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Norte', [$box->id]);

    expect(fn () => app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$box->id]))
        ->toThrow(InventoryException::class);
    expect(fn () => DB::table('exit_items')->insert([
        'exit_id' => DB::table('exits')->value('id'), 'box_id' => $box->id, 'kind' => 'full', 'weight_before_kg' => 20, 'weight_out_kg' => 20,
        'weight_after_kg' => 0, 'unit_cost_snapshot_mxn' => 1, 'cost_out_mxn' => 1, 'is_active' => 1, 'holds_box' => 1,
    ]))->toThrow(Exception::class);
});

it('releases a missing box once an administrator finds it again', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $box = Box::query()->first();
    $transfer = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Norte', [$box->id])->transfer;
    app(ReceiveTransfer::class)->handle($w['norteUser'], $transfer, []);

    expect(DB::table('exit_items')->where('box_id', $box->id)->value('holds_box'))->toBe(1);

    app(AdjustInventory::class)->handle($w['admin'], $box->fresh(), AdjustmentType::Found, 'Apareció en el almacén', chamber: $w['n1']);

    expect(DB::table('exit_items')->where('box_id', $box->id)->value('holds_box'))->toBe(0);
    $delivery = app(RegisterExit::class)->handle($w['norteUser'], $w['n1'], $w['sur'], null, 'Ana', [$box->id]);
    expect($delivery->total_boxes)->toBe(1);
});

it('lets only the super administrator correct an entry, keeping the stock, the histories and the audit in sync', function () {
    $w = warehouseWorld();
    $entry = registerExampleEntry($w);
    $items = $entry->items()->orderBy('id')->get();

    expect(fn () => app(CorrectEntry::class)->handle($w['centroUser'], $entry, 'Error al capturar', null, 'Nota', []))
        ->toThrow(AuthorizationException::class);

    $corrected = app(CorrectEntry::class)->handle($w['admin'], $entry, 'El peso y el costo estaban mal', null, 'Corregida por el administrador', [
        $items[0]->id => ['boxes_count' => 12, 'weight_per_box_kg' => '21', 'price_per_kg_mxn' => '24.00'],
        $items[2]->id => ['boxes_count' => 6, 'weight_per_box_kg' => '18', 'price_per_kg_mxn' => '26.00'],
    ]);

    // 12×21@510 + 15×22@550 + 6×18@470
    expect($corrected->total_boxes)->toBe(33)
        ->and($corrected->total_weight_kg)->toBe('690.000')
        ->and($corrected->total_cost_mxn)->toBe('17106.00')
        ->and($corrected->notes)->toBe('Corregida por el administrador')
        ->and(Box::query()->where('entry_item_id', $items[0]->id)->whereNull('voided_at')->count())->toBe(12)
        ->and(Box::query()->where('entry_item_id', $items[0]->id)->whereNull('voided_at')->where('initial_weight_kg', 21)->where('current_weight_kg', 21)->where('unit_cost_mxn', 504)->count())->toBe(12)
        ->and(Box::query()->where('entry_item_id', $items[2]->id)->whereNotNull('voided_at')->where('status', BoxStatus::WrittenOff)->count())->toBe(2)
        ->and(Box::query()->where('status', BoxStatus::Available)->count())->toBe(33)
        ->and(Box::query()->distinct()->count('barcode'))->toBe(Box::query()->count());

    $added = Box::query()->where('entry_item_id', $items[0]->id)->orderByDesc('id')->first();
    $history = DB::table('box_movements')->where('box_id', $added->id)->pluck('type')->all();
    expect($history)->toBe([MovementType::Entry->value]);

    $first = Box::query()->where('entry_item_id', $items[0]->id)->orderBy('id')->first();
    expect(DB::table('box_movements')->where('box_id', $first->id)->where('type', MovementType::Adjustment->value)->value('notes'))->toContain('El peso y el costo estaban mal');

    $log = DB::table('audit_logs')->where('event', 'entry.corrected')->first();
    expect($log)->not->toBeNull()->and(json_decode($log->new_values, true)['reason'])->toBe('El peso y el costo estaban mal');

    expect(fn () => app(CorrectEntry::class)->handle($w['admin'], $corrected, 'Sin cambios reales', null, null, []))
        ->toThrow(InventoryException::class, 'No hay cambios');
});

it('refuses to change the boxes of an entry whose boxes already moved, but still lets the notes change', function () {
    $w = warehouseWorld();
    $entry = registerExampleEntry($w);
    $items = $entry->items()->orderBy('id')->get();
    $box = Box::query()->where('entry_item_id', $items[0]->id)->orderBy('id')->first();
    app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$box->id]);

    expect(fn () => app(CorrectEntry::class)->handle($w['admin'], $entry, 'Peso mal capturado', null, null, [
        $items[0]->id => ['boxes_count' => 10, 'weight_per_box_kg' => '19', 'price_per_kg_mxn' => '26.00'],
    ]))->toThrow(InventoryException::class, 'Ya hubo movimientos');

    // The other groups are untouched, so they can still be corrected.
    app(CorrectEntry::class)->handle($w['admin'], $entry, 'Costo mal capturado', null, 'Nueva nota', [
        $items[1]->id => ['boxes_count' => 15, 'weight_per_box_kg' => '22', 'price_per_kg_mxn' => '26.00'],
    ]);

    expect($entry->fresh()->notes)->toBe('Nueva nota')
        ->and($items[0]->fresh()->weight_per_box_kg)->toBe('20.000')
        ->and($items[1]->fresh()->unit_cost_mxn)->toBe('572.00');
});

it('lets the super administrator fix the header, the destination and the boxes of a delivery', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $boxes = Box::query()->orderBy('id')->limit(4)->get();
    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$boxes[0]->id, $boxes[1]->id, $boxes[2]->id]);

    expect(fn () => app(CorrectExit::class)->handle($w['centroUser'], $exit, 'Se equivocó', 'Luis'))
        ->toThrow(AuthorizationException::class);

    $corrected = app(CorrectExit::class)->handle(
        $w['admin'], $exit, 'Se entregó una caja equivocada', 'Luis Pérez', 'Entrega corregida', now()->subDay(), $w['norte'],
        [$boxes[2]->id], [$boxes[3]->id],
    );

    expect($corrected->requester_name)->toBe('Luis Pérez')
        ->and($corrected->dest_branch_id)->toBe($w['norte']->id)
        ->and($corrected->total_boxes)->toBe(3)
        ->and($boxes[2]->fresh()->status)->toBe(BoxStatus::Available)
        ->and($boxes[2]->fresh()->current_chamber_id)->toBe($w['c1']->id)
        ->and($boxes[3]->fresh()->status)->toBe(BoxStatus::Dispatched)
        ->and(DB::table('exit_items')->where('exit_id', $exit->id)->where('is_active', 1)->pluck('box_id')->sort()->values()->all())
        ->toBe([$boxes[0]->id, $boxes[1]->id, $boxes[3]->id]);

    expect($corrected->total_weight_kg)->toBe('60.000')
        ->and($corrected->total_cost_mxn)->toBe('1500.00');

    // The removed box can be added back (its exit row is reactivated, not duplicated).
    app(CorrectExit::class)->handle($w['admin'], $corrected, 'Era la caja correcta', 'Luis Pérez', null, null, null, [$boxes[3]->id], [$boxes[2]->id]);
    expect(DB::table('exit_items')->where('exit_id', $exit->id)->where('box_id', $boxes[2]->id)->count())->toBe(1)
        ->and($boxes[3]->fresh()->status)->toBe(BoxStatus::Available);

    expect(fn () => app(CorrectExit::class)->handle($w['admin'], $corrected->fresh(), 'Quitar todo', 'Luis', null, null, null, [$boxes[0]->id, $boxes[1]->id, $boxes[2]->id]))
        ->toThrow(InventoryException::class, 'al menos una caja');
});

it('only changes the requester and notes of an exit that created a transfer', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $box = Box::query()->first();
    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Norte', [$box->id]);

    expect(fn () => app(CorrectExit::class)->handle($w['admin'], $exit, 'Quitar caja', 'Norte', null, null, null, [$box->id]))
        ->toThrow(InventoryException::class)
        ->and(fn () => app(CorrectExit::class)->handle($w['admin'], $exit, 'Cambiar fecha', 'Norte', null, now()->subDays(2)))
        ->toThrow(InventoryException::class);

    $corrected = app(CorrectExit::class)->handle($w['admin'], $exit, 'Nombre mal escrito', 'Sucursal Norte', 'Con prisa');

    expect($corrected->requester_name)->toBe('Sucursal Norte')
        ->and($corrected->notes)->toBe('Con prisa')
        ->and($exit->transfer->fresh()->status)->toBe(TransferStatus::InTransit);
});

it('lets only the super administrator settle the stock of a product in a chamber', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $service = app(AdjustStockSummary::class);

    expect(fn () => $service->handle($w['centroUser'], $w['c1'], $w['product'], 'Conteo físico', 30))->toThrow(AuthorizationException::class)
        ->and(fn () => $service->handle($w['admin'], $w['c1'], $w['product'], 'Conteo físico'))->toThrow(InventoryException::class, 'No hay cambios')
        ->and(fn () => $service->handle($w['admin'], $w['c1'], $w['product'], 'x', 30))->toThrow(InventoryException::class, 'motivo');

    // Fewer boxes than the system says: the oldest ones are written off, not deleted.
    $result = $service->handle($w['admin'], $w['c1'], $w['product'], 'Conteo físico', 30);

    expect($result['written_off'])->toBe(3)
        ->and(Box::query()->where('status', BoxStatus::Available)->count())->toBe(30)
        ->and(Box::query()->where('status', BoxStatus::WrittenOff)->orderBy('id')->pluck('id')->all())->toBe(Box::query()->orderBy('id')->limit(3)->pluck('id')->all())
        ->and(DB::table('audit_logs')->where('event', 'stock.adjusted')->count())->toBe(1);

    // More boxes, a new weight and a new cost: the remaining boxes follow, the new ones are created.
    $result = $service->handle($w['admin'], $w['c1'], $w['product'], 'Llegó mercancía sin registrar', 32, '25', '24');

    expect($result['added'])->toBe(2)
        ->and($result['reweighed'])->toBe(30)
        ->and(Box::query()->where('status', BoxStatus::Available)->where('initial_weight_kg', 25)->where('current_weight_kg', 25)->where('unit_cost_mxn', 600)->count())->toBe(32)
        ->and(Box::query()->distinct()->count('barcode'))->toBe(Box::query()->count());

    $added = Box::query()->orderByDesc('id')->first();
    $movement = DB::table('box_movements')->where('box_id', $added->id)->first();
    expect($movement->type)->toBe(MovementType::Adjustment->value)->and($movement->status_before)->toBeNull();
});

it('keeps the weight of partial boxes and uses the average for the boxes it creates', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $partial = Box::query()->orderBy('id')->first();
    app(AdjustInventory::class)->handle($w['admin'], $partial, AdjustmentType::WeightCorrection, 'Merma real', '10');

    $result = app(AdjustStockSummary::class)->handle($w['admin'], $w['c1'], $w['product'], 'Se pesó de nuevo', null, '25');

    expect($result['skipped_partial'])->toBe(1)
        ->and($result['reweighed'])->toBe(32)
        ->and($partial->fresh()->current_weight_kg)->toBe('10.000');

    $result = app(AdjustStockSummary::class)->handle($w['admin'], $w['c1'], $w['product'], 'Faltaba una caja', 34);
    $created = Box::query()->orderByDesc('id')->first();

    // Average of the 32 boxes of 25 kg and the partial one of 10 kg.
    expect($result['added'])->toBe(1)->and($created->current_weight_kg)->toBe('24.545');
});

it('lets only the super administrator edit the weight and cost of a box', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $box = Box::query()->orderBy('id')->first();
    $service = app(EditBox::class);

    expect(fn () => $service->handle($w['centroUser'], $box, 'Peso mal capturado', '19'))->toThrow(AuthorizationException::class)
        ->and(fn () => $service->handle($w['admin'], $box, 'Peso mal capturado'))->toThrow(InventoryException::class, 'No hay cambios')
        ->and(fn () => $service->handle($w['admin'], $box, 'x', '19'))->toThrow(InventoryException::class, 'motivo')
        ->and(fn () => $service->handle($w['admin'], $box, 'Peso mal capturado', '0'))->toThrow(InventoryException::class, 'mayor a cero');

    // An intact box stays intact: initial and current weight both change.
    $edited = $service->handle($w['admin'], $box, 'Peso y costo mal capturados', '19.5', '26');

    expect($edited->initial_weight_kg)->toBe('19.500')
        ->and($edited->current_weight_kg)->toBe('19.500')
        ->and($edited->unit_cost_mxn)->toBe('507.00')
        ->and($edited->isPartial())->toBeFalse()
        ->and(DB::table('audit_logs')->where('event', 'box.edited')->count())->toBe(1);

    $movement = DB::table('box_movements')->where('box_id', $box->id)->orderByDesc('id')->first();
    expect($movement->type)->toBe(MovementType::Adjustment->value)->and($movement->notes)->toContain('Peso y costo mal capturados');
});

it('only changes the current weight of a partial box and refuses a box that is not available', function () {
    $w = warehouseWorld();
    registerExampleEntry($w);
    $boxes = Box::query()->orderBy('id')->limit(2)->get();
    app(AdjustInventory::class)->handle($w['admin'], $boxes[0], AdjustmentType::WeightCorrection, 'Merma real', '10');
    $service = app(EditBox::class);

    expect(fn () => $service->handle($w['admin'], $boxes[0]->fresh(), 'Peso mal capturado', '25'))->toThrow(InventoryException::class, 'no puede superar');

    $edited = $service->handle($w['admin'], $boxes[0]->fresh(), 'Se pesó de nuevo', '12');
    expect($edited->current_weight_kg)->toBe('12.000')->and($edited->initial_weight_kg)->toBe('20.000');

    app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$boxes[1]->id]);
    expect(fn () => $service->handle($w['admin'], $boxes[1]->fresh(), 'Caja ya entregada', '15'))->toThrow(InventoryException::class, 'no está disponible');
});

it('calculates the cost per kilo: 7 boxes of 20 kg at $200 per kg is $28,000', function () {
    $w = warehouseWorld();

    $entry = app(RegisterEntry::class)->handle($w['centroUser'], $w['c1'], [
        ['product_id' => $w['product']->id, 'boxes_count' => 7, 'weight_per_box_kg' => '20', 'price_per_kg_mxn' => '200'],
    ]);
    $box = Box::query()->firstOrFail();

    expect($entry->total_cost_mxn)->toBe('28000.00')
        ->and($box->price_per_kg_mxn)->toBe('200.00')
        ->and($box->unit_cost_mxn)->toBe('4000.00');

    $edited = app(EditBox::class)->handle($w['admin'], $box, 'Peso mal capturado', '10');
    expect($edited->unit_cost_mxn)->toBe('2000.00');

    $partial = app(WithdrawPartial::class)->handle($w['centroUser'], $edited->fresh(), '5', $w['sur'], 'Ana');
    expect($partial->total_cost_mxn)->toBe('1000.00');
});
