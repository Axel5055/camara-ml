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
    $super = User::factory()->superAdmin()->create();
    $pollo = Product::factory()->create(['code' => 'POLLO', 'name' => 'Pollo entero']);
    $tilapia = Product::factory()->create(['code' => 'TIL08PIA12MC000', 'name' => 'Tilapia MC']);

    return compact('centro', 'sur', 'c1', 'user', 'super', 'pollo', 'tilapia');
}

it('shows in the entry table the barcodes that each group will receive', function () {
    $w = barcodeWorld();
    $d = now()->format('d');
    $m = now()->format('m');

    $component = Livewire::actingAs($w['super'])->test(CreateEntry::class)
        ->set('productId', $w['pollo']->id)->set('boxesCount', '3')->set('weight', '10')->set('cost', '50')
        ->call('addLine')
        ->assertSee("POL{$d}LOE{$m}NTE000001")
        ->assertSee("POL{$d}LOE{$m}NTE000003")
        ->assertDontSee("POL{$d}LOE{$m}NTE000002");

    $component
        ->set('productId', $w['tilapia']->id)->set('boxesCount', '2')->set('weight', '12')->set('cost', '80')
        ->call('addLine')
        ->assertSee("TIL{$d}API{$m}AMC000004")
        ->assertSee("TIL{$d}API{$m}AMC000005")
        ->assertSee('Estos son los códigos de barras que se asignarán');
});

it('continues the preview from the current global counter and keeps manual labels', function () {
    $w = barcodeWorld();
    $d = now()->format('d');
    $m = now()->format('m');
    DB::table('sequences')->where('name', 'barcode')->update(['last_value' => 120]);

    Livewire::actingAs($w['super'])->test(CreateEntry::class)
        ->set('productId', $w['pollo']->id)->set('boxesCount', '3')->set('weight', '10')->set('cost', '50')
        ->set('manual', 'etiq-9')->set('origin', 'Proveedor X')
        ->call('addLine')
        ->assertSee("POL{$d}LOE{$m}NTE000121")
        ->assertSee("POL{$d}LOE{$m}NTE000122")
        ->assertDontSee("POL{$d}LOE{$m}NTE000123")
        ->assertSee('ETIQ-9');
});

it('registers the entry with exactly the barcodes that were previewed', function () {
    $w = barcodeWorld();
    $d = now()->format('d');
    $m = now()->format('m');

    Livewire::actingAs($w['super'])->test(CreateEntry::class)
        ->set('chamberId', $w['c1']->id)
        ->set('productId', $w['pollo']->id)->set('boxesCount', '2')->set('weight', '10')->set('cost', '50')
        ->call('addLine')
        ->call('save');

    expect(Box::query()->orderBy('id')->pluck('barcode')->all())->toBe(["POL{$d}LOE{$m}NTE000001", "POL{$d}LOE{$m}NTE000002"]);
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
    $d = now()->format('d');
    $m = now()->format('m');

    Livewire::actingAs($w['super'])->test(CreateEntry::class)
        ->set('productId', $w['pollo']->id)->set('boxesCount', '5')->set('weight', '10')->set('cost', '50')
        ->call('addLine')
        ->assertSee('Ver códigos de barras')
        ->assertSee('Del')
        ->assertSee("POL{$d}LOE{$m}NTE000001")
        ->assertSee("POL{$d}LOE{$m}NTE000005")
        ->assertSee('5 códigos consecutivos');
});

it('previews the totals of the group being captured before it is added', function () {
    $w = barcodeWorld();

    Livewire::actingAs($w['user'])->test(CreateEntry::class)
        ->assertDontSeeHtml('data-test="entry-draft"')
        ->set('boxesCount', '4')->set('weight', '12.5')->set('cost', '80')
        ->assertSeeHtml('data-test="entry-draft"')
        ->assertSee('50.000 kg')
        ->assertDontSee('$320.00');
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

it('does not add boxes for a product that does not exist', function () {
    $w = barcodeWorld();

    Livewire::actingAs($w['super'])->test(CreateEntry::class)
        ->set('chamberId', $w['c1']->id)
        ->set('productId', null)->set('boxesCount', '2')->set('weight', '10')->set('cost', '50')
        ->call('addLine')
        ->assertHasErrors(['productId'])
        ->set('productId', 999999)
        ->call('addLine')
        ->assertHasErrors(['productId']);
});

it('starts the entry and exit forms without a chamber and shows each missing chamber message only once', function () {
    $w = barcodeWorld();

    $entry = Livewire::actingAs($w['super'])->test(CreateEntry::class)->assertSet('chamberId', null);
    $html = $entry->call('save')->assertHasErrors(['chamberId' => 'required'])->html();
    expect(substr_count($html, 'La cámara es un dato obligatorio.'))->toBe(1);

    Livewire::actingAs($w['user'])->test(CreateExit::class)->assertSet('originChamberId', null);
});
