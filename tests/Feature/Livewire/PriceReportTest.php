<?php

use App\Livewire\Reports\PriceReport;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Chamber;
use App\Models\Entry;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\RegisterEntry;
use App\Services\Inventory\ReverseEntry;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

function priceWorld(): array
{
    $branch = Branch::factory()->create(['name' => 'Centro']);
    $chamber = Chamber::factory()->for($branch)->create(['name' => 'Centro 1']);
    $superAdmin = User::factory()->superAdmin()->create();
    $warehouse = User::factory()->warehouse($branch)->create();
    $warehouse->chambers()->attach($chamber);
    $category = Category::factory()->create(['name' => 'POLLO']);
    $pollo = Product::factory()->create(['category_id' => $category->id, 'code' => 'POLLO', 'name' => 'POLLO ENTERO']);
    $alas = Product::factory()->create(['category_id' => $category->id, 'code' => 'ALAS', 'name' => 'ALAS DE POLLO']);
    $sinPrecio = Product::factory()->create(['category_id' => $category->id, 'code' => 'NUEVO', 'name' => 'PRODUCTO NUEVO']);

    return compact('branch', 'chamber', 'superAdmin', 'warehouse', 'pollo', 'alas', 'sinPrecio');
}

function priceEntry(array $w, Product $product, string $price, string $date): Entry
{
    return app(RegisterEntry::class)->handle($w['superAdmin'], $w['chamber'], [
        ['product_id' => $product->id, 'boxes_count' => 1, 'weight_per_box_kg' => '10', 'price_per_kg_mxn' => $price],
    ], Carbon::parse($date));
}

it('lets super administrators, managers and warehouse users look at the prices, and nobody else', function () {
    $w = priceWorld();

    foreach ([$w['superAdmin'], User::factory()->manager()->create(), $w['warehouse']] as $user) {
        $this->actingAs($user)->get(route('reports.prices'))->assertOk()->assertSee('Precios por kilo');
    }

    $this->actingAs($w['warehouse'])->get(route('dashboard'))->assertSee('Reportes')->assertSee('Precios por kilo')->assertDontSee('Existencias bajas');

    foreach ([User::factory()->admin()->create(), User::factory()->requester(Branch::factory()->create())->create()] as $user) {
        $this->actingAs($user)->get(route('reports.prices'))->assertForbidden();
    }
});

it('shows the latest price of every product, with or without stock, and the change against the previous one', function () {
    $w = priceWorld();
    priceEntry($w, $w['pollo'], '40.00', '2026-01-10 09:00:00');
    priceEntry($w, $w['pollo'], '45.00', '2026-02-10 09:00:00');
    priceEntry($w, $w['pollo'], '45.00', '2026-02-20 09:00:00');
    priceEntry($w, $w['alas'], '60.00', '2026-01-15 09:00:00');

    $component = Livewire::actingAs($w['warehouse'])->test(PriceReport::class)
        ->assertSee('POLLO ENTERO')->assertSee('ALAS DE POLLO')->assertSee('PRODUCTO NUEVO')->assertSee('Sin precio registrado todavía.');

    $rows = $component->viewData('products')->keyBy('id');
    expect($rows[$w['pollo']->id]->price_changes->pluck('price')->all())->toBe(['40.00', '45.00'])
        ->and($rows[$w['pollo']->id]->price_changes->last()['change'])->toBe('5.00')
        ->and($rows[$w['pollo']->id]->price_changes->last()['since'])->toBe('2026-02-10')
        ->and($rows[$w['alas']->id]->price_changes->last()['change'])->toBeNull()
        ->and($rows[$w['sinPrecio']->id]->price_changes)->toBeEmpty();

    $component->assertSeeHtml('$45.00')->assertSee('Primer precio');
});

it('opens the history of a product from the newest price to the oldest, merging repeated prices', function () {
    $w = priceWorld();
    priceEntry($w, $w['pollo'], '40.00', '2026-01-10 09:00:00');
    priceEntry($w, $w['pollo'], '45.00', '2026-02-10 09:00:00');
    priceEntry($w, $w['pollo'], '45.00', '2026-02-20 09:00:00');
    priceEntry($w, $w['pollo'], '42.00', '2026-03-01 09:00:00');

    $component = Livewire::actingAs($w['superAdmin'])->test(PriceReport::class)->call('showHistory', $w['pollo']->id)
        ->assertSeeHtml('data-test="price-history-table"')->assertSee('Historial de precios');

    expect($component->viewData('history')->pluck('price')->all())->toBe(['42.00', '45.00', '40.00'])
        ->and($component->viewData('history')->pluck('change')->all())->toBe(['-3.00', '5.00', null]);
});

it('leaves out the prices of reversed entries and filters by product or category', function () {
    $w = priceWorld();
    priceEntry($w, $w['pollo'], '40.00', '2026-01-10 09:00:00');
    $wrong = priceEntry($w, $w['pollo'], '99.00', '2026-02-10 09:00:00');
    priceEntry($w, $w['alas'], '60.00', '2026-01-15 09:00:00');
    app(ReverseEntry::class)->handle($w['superAdmin'], $wrong, 'Captura equivocada');

    $rows = Livewire::actingAs($w['superAdmin'])->test(PriceReport::class)->viewData('products')->keyBy('id');
    expect($rows[$w['pollo']->id]->price_changes->pluck('price')->all())->toBe(['40.00']);

    $component = Livewire::actingAs($w['superAdmin'])->test(PriceReport::class)->set('productId', $w['alas']->id);
    expect($component->viewData('products')->pluck('name')->all())->toBe(['ALAS DE POLLO']);

    $component->call('clearFilters');
    expect($component->viewData('products'))->toHaveCount(3);

    $component->set('categoryId', Category::factory()->create()->id)->assertSee('Ningún producto coincide con los filtros.');
});
