<?php

use App\Enums\BoxStatus;
use App\Livewire\Inventory\CreateEntry;
use App\Livewire\Inventory\CreateExit;
use App\Livewire\Inventory\CreateTransfer;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\RegisterEntry;
use Livewire\Livewire;

/**
 * @return array<string, mixed>
 */
function draftWorld(): array
{
    $branch = Branch::factory()->create();
    $mine = Chamber::factory()->for($branch)->create();
    $other = Chamber::factory()->for(Branch::factory()->create())->create();
    $user = User::factory()->warehouse($branch)->create();
    $user->chambers()->attach($mine);
    $product = Product::factory()->create(['name' => 'POLLO', 'code' => 'POLLO']);

    app(RegisterEntry::class)->handle($user, $mine, [
        ['product_id' => $product->id, 'boxes_count' => 3, 'weight_per_box_kg' => '20', 'price_per_kg_mxn' => '6.00'],
    ]);

    return ['branch' => $branch, 'mine' => $mine, 'other' => $other, 'user' => $user, 'product' => $product, 'boxes' => Box::query()->orderBy('id')->get()];
}

it('shows the draft recovery on the capture screens', function () {
    $w = draftWorld();
    $this->actingAs($w['user']);

    foreach (['entries.create', 'exits.create', 'transfers.create'] as $route) {
        $this->get(route($route))->assertOk()->assertSee('data-test="draft-recovery"', false);
    }
});

it('restores an entry draft rebuilding every line from the catalog', function () {
    $w = draftWorld();
    $token = (string) Str::uuid();

    Livewire::actingAs($w['user'])->test(CreateEntry::class)
        ->call('restoreDraft', [
            'chamberId' => $w['mine']->id,
            'notes' => 'Remisión 15',
            'clientToken' => $token,
            'lines' => [
                ['product_id' => $w['product']->id, 'product' => 'NOMBRE FALSO', 'code' => 'X', 'boxes_count' => 5, 'weight' => '20', 'cost' => '6', 'manual' => [], 'origin' => null],
            ],
            'productId' => $w['product']->id,
            'boxesCount' => '2',
            'weight' => '10.5',
            'cost' => '3',
        ])
        ->assertSet('chamberId', $w['mine']->id)
        ->assertSet('notes', 'Remisión 15')
        ->assertSet('clientToken', $token)
        ->assertSet('lines.0.product', 'POLLO')
        ->assertSet('lines.0.weight', '20.000')
        ->assertSet('lines.0.cost', '6.00')
        ->assertSet('boxesCount', '2')
        ->assertSet('weight', '10.5');
});

it('leaves out invalid lines, foreign chambers and bad tokens when restoring an entry', function () {
    $w = draftWorld();
    Livewire::actingAs($w['user'])->test(CreateEntry::class)
        ->call('restoreDraft', [
            'chamberId' => $w['other']->id,
            'clientToken' => 'no-es-un-uuid',
            'lines' => [
                ['product_id' => 999999, 'boxes_count' => 1, 'weight' => '20', 'cost' => '6'],
                ['product_id' => $w['product']->id, 'boxes_count' => 0, 'weight' => '20', 'cost' => '6'],
                ['product_id' => $w['product']->id, 'boxes_count' => 2, 'weight' => '-4', 'cost' => '6'],
                ['product_id' => $w['product']->id, 'boxes_count' => 2, 'weight' => '20', 'cost' => '6', 'manual' => ['ABC'], 'origin' => ''],
                ['product_id' => $w['product']->id, 'boxes_count' => 4, 'weight' => '20', 'cost' => '6'],
            ],
        ])
        ->assertSet('chamberId', $w['mine']->id)
        ->assertSet('lines', fn (array $lines) => count($lines) === 1 && $lines[0]['boxes_count'] === 4)
        ->assertNotSet('clientToken', 'no-es-un-uuid');
});

it('only brings back the boxes that are still available when restoring an exit', function () {
    $w = draftWorld();
    $w['boxes'][2]->forceFill(['status' => BoxStatus::Dispatched, 'current_chamber_id' => null])->save();

    Livewire::actingAs($w['user'])->test(CreateExit::class)
        ->call('restoreDraft', [
            'originChamberId' => $w['mine']->id,
            'destBranchId' => $w['other']->branch_id,
            'requester' => 'Juan',
            'notes' => 'Urgente',
            'lines' => [['box_ids' => [$w['boxes'][0]->id, $w['boxes'][1]->id, $w['boxes'][2]->id, 987654]]],
        ])
        ->assertSet('originChamberId', $w['mine']->id)
        ->assertSet('destBranchId', $w['other']->branch_id)
        ->assertSet('requester', 'Juan')
        ->assertSet('lines', fn (array $lines) => count($lines) === 1
            && $lines[0]['box_ids'] === [$w['boxes'][0]->id, $w['boxes'][1]->id]
            && $lines[0]['product'] === 'POLLO');
});

it('does not restore boxes from a chamber the user cannot operate', function () {
    $w = draftWorld();
    $stranger = User::factory()->warehouse($w['other']->branch)->create();
    $stranger->chambers()->attach($w['other']);

    Livewire::actingAs($stranger)->test(CreateExit::class)
        ->call('restoreDraft', [
            'originChamberId' => $w['mine']->id,
            'lines' => [['box_ids' => $w['boxes']->pluck('id')->all()]],
        ])
        ->assertSet('originChamberId', $w['other']->id)
        ->assertSet('lines', []);
});

it('restores a transfer draft with its destination chamber', function () {
    $w = draftWorld();

    Livewire::actingAs($w['user'])->test(CreateTransfer::class)
        ->call('restoreDraft', [
            'originChamberId' => $w['mine']->id,
            'destChamberId' => $w['other']->id,
            'requester' => 'Ana',
            'lines' => [['box_ids' => [$w['boxes'][0]->id]]],
        ])
        ->assertSet('destChamberId', $w['other']->id)
        ->assertSet('lines', fn (array $lines) => $lines[0]['box_ids'] === [$w['boxes'][0]->id]);

    Livewire::actingAs($w['user'])->test(CreateTransfer::class)
        ->call('restoreDraft', ['originChamberId' => $w['mine']->id, 'destChamberId' => $w['mine']->id])
        ->assertSet('destChamberId', null);
});
