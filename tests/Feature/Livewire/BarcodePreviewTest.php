<?php

use App\Livewire\Inventory\CreateEntry;
use App\Livewire\Inventory\CreateExit;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\RegisterEntry;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * @return array<string, mixed>
 */
function barcodeWorld(): array
{
    $centro = Branch::factory()->create();
    $sur = Branch::factory()->create();
    $c1 = Chamber::factory()->for($centro)->create();
    $user = User::factory()->warehouse($centro)->create();
    $user->chambers()->attach($c1);
    $pollo = Product::factory()->create(['code' => 'POLLO', 'name' => 'Pollo']);
    $tilapia = Product::factory()->create(['code' => 'TIL08PIA12MC000', 'name' => 'Tilapia']);

    return compact('centro', 'sur', 'c1', 'user', 'pollo', 'tilapia');
}

it('shows in the entry table the barcodes that each group will receive', function () {
    $w = barcodeWorld();
    $today = now()->format('Ymd');

    $component = Livewire::actingAs($w['user'])->test(CreateEntry::class)
        ->set('productId', $w['pollo']->id)->set('boxesCount', '3')->set('weight', '10')->set('cost', '50')
        ->call('addLine')
        ->assertSee("POLLO-{$today}-000001")
        ->assertSee("POLLO-{$today}-000003")
        ->assertDontSee("POLLO-{$today}-000002");

    $component
        ->set('productId', $w['tilapia']->id)->set('boxesCount', '2')->set('weight', '12')->set('cost', '80')
        ->call('addLine')
        ->assertSee("TIL08PIA12MC000-{$today}-000004")
        ->assertSee("TIL08PIA12MC000-{$today}-000005")
        ->assertSee('Estos son los códigos de barras que se asignarán');
});

it('continues the preview from the current global counter and keeps manual labels', function () {
    $w = barcodeWorld();
    $today = now()->format('Ymd');
    DB::table('sequences')->where('name', 'barcode')->update(['last_value' => 120]);

    Livewire::actingAs($w['user'])->test(CreateEntry::class)
        ->set('productId', $w['pollo']->id)->set('boxesCount', '3')->set('weight', '10')->set('cost', '50')
        ->set('manual', 'etiq-9')->set('origin', 'Proveedor X')
        ->call('addLine')
        ->assertSee("POLLO-{$today}-000121")
        ->assertSee("POLLO-{$today}-000122")
        ->assertDontSee("POLLO-{$today}-000123")
        ->assertSee('ETIQ-9');
});

it('registers the entry with exactly the barcodes that were previewed', function () {
    $w = barcodeWorld();
    $today = now()->format('Ymd');

    Livewire::actingAs($w['user'])->test(CreateEntry::class)
        ->set('chamberId', $w['c1']->id)
        ->set('productId', $w['pollo']->id)->set('boxesCount', '2')->set('weight', '10')->set('cost', '50')
        ->call('addLine')
        ->call('save');

    expect(Box::query()->orderBy('id')->pluck('barcode')->all())->toBe(["POLLO-{$today}-000001", "POLLO-{$today}-000002"]);
});

it('lists the barcodes taken in the exit table, under the product name', function () {
    $w = barcodeWorld();
    app(RegisterEntry::class)->handle($w['user'], $w['c1'], [
        ['product_id' => $w['pollo']->id, 'boxes_count' => 6, 'weight_per_box_kg' => '10', 'unit_cost_mxn' => '50'],
    ]);
    $boxes = Box::query()->orderBy('id')->get();

    Livewire::actingAs($w['user'])->test(CreateExit::class)
        ->set('originChamberId', $w['c1']->id)
        ->set('productId', $w['pollo']->id)
        ->set('quantity', '3')
        ->call('addProduct')
        ->assertSee('Ver códigos')
        ->assertSee($boxes[0]->barcode)
        ->assertSee($boxes[1]->barcode)
        ->assertSee($boxes[2]->barcode)
        ->assertDontSee($boxes[3]->barcode);
});

it('shows the barcode range of an entry group inside the collapsible under the product name', function () {
    $w = barcodeWorld();
    $today = now()->format('Ymd');

    Livewire::actingAs($w['user'])->test(CreateEntry::class)
        ->set('productId', $w['pollo']->id)->set('boxesCount', '5')->set('weight', '10')->set('cost', '50')
        ->call('addLine')
        ->assertSee('Ver códigos de barras')
        ->assertSee('Del')
        ->assertSee("POLLO-{$today}-000001")
        ->assertSee("POLLO-{$today}-000005")
        ->assertSee('5 códigos consecutivos');
});

it('previews the totals of the group being captured before it is added', function () {
    $w = barcodeWorld();

    Livewire::actingAs($w['user'])->test(CreateEntry::class)
        ->assertDontSeeHtml('data-test="entry-draft"')
        ->set('boxesCount', '4')->set('weight', '12.5')->set('cost', '80')
        ->assertSeeHtml('data-test="entry-draft"')
        ->assertSee('50.000 kg')
        ->assertSee('$320.00');
});

it('shows the route of the exit being captured', function () {
    $w = barcodeWorld();

    Livewire::actingAs($w['user'])->test(CreateExit::class)
        ->set('originChamberId', $w['c1']->id)
        ->set('destBranchId', $w['sur']->id)
        ->assertSeeHtml('data-test="exit-new-route"')
        ->assertSee($w['c1']->name)
        ->assertSee($w['sur']->name);
});
