<?php

use App\Livewire\Inventory\Stock;
use App\Livewire\Reports\EntriesReport;
use App\Livewire\Reports\LowStock;
use App\Models\Chamber;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\RegisterEntry;
use Livewire\Livewire;

function stockOneBox(Product $product, ?Chamber $chamber = null): void
{
    app(RegisterEntry::class)->handle(User::factory()->superAdmin()->create(), $chamber ?? Chamber::factory()->create(), [
        ['product_id' => $product->id, 'boxes_count' => 1, 'weight_per_box_kg' => '10', 'price_per_kg_mxn' => '20.00'],
    ]);
}

it('uses the product search box instead of a select in stock and the reports', function () {
    $product = Product::factory()->create(['name' => 'POLLO ENTERO', 'code' => 'POLLO']);
    $this->actingAs(User::factory()->superAdmin()->create());
    stockOneBox($product);

    Livewire::test(Stock::class)
        ->assertSeeHtml('data-test="stock-product-search"')
        ->assertSeeHtml('POLLO ENTERO')
        ->assertDontSeeHtml('<option value="'.$product->id.'"');

    Livewire::test(EntriesReport::class)
        ->assertSeeHtml('data-test="report-product-search"')
        ->assertDontSeeHtml('<option value="'.$product->id.'"');

    Livewire::test(LowStock::class)
        ->assertSeeHtml('data-test="threshold-product-search"')
        ->assertDontSeeHtml('<option value="'.$product->id.'"');
});

it('keeps filtering by the product picked in the search box', function () {
    $pollo = Product::factory()->create(['name' => 'POLLO ENTERO', 'code' => 'POLLO']);
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(EntriesReport::class)
        ->set('productId', $pollo->id)
        ->assertSet('productId', $pollo->id)
        ->call('clearFilters')
        ->assertSet('productId', null);
});

it('offers a refresh button that reloads the product list without losing what is typed', function () {
    $product = Product::factory()->create(['name' => 'POLLO ENTERO', 'code' => 'POLLO']);
    $this->actingAs(User::factory()->superAdmin()->create());

    $component = Livewire::test(Stock::class)
        ->set('search', 'texto en captura')
        ->assertSeeHtml('data-test="stock-product-search-refresh"');

    $newProduct = Product::factory()->create(['name' => 'ALAS DE POLLO', 'code' => 'ALAS']);
    stockOneBox($newProduct);

    $component->call('$refresh')
        ->assertSet('search', 'texto en captura')
        ->assertSeeHtml('ALAS DE POLLO');

    expect($newProduct->exists)->toBeTrue()->and($product->exists)->toBeTrue();
});

it('offers in the stock filter only the products with boxes in the chambers being looked at', function () {
    $mine = Chamber::factory()->create();
    $other = Chamber::factory()->create();
    $warehouse = User::factory()->warehouse($mine->branch)->create();
    $warehouse->chambers()->attach($mine);

    $here = Product::factory()->create(['name' => 'POLLO ENTERO', 'code' => 'POLLO']);
    $elsewhere = Product::factory()->create(['name' => 'ALAS DE POLLO', 'code' => 'ALAS']);
    Product::factory()->create(['name' => 'SIN EXISTENCIAS', 'code' => 'NADA']);
    stockOneBox($here, $mine);
    stockOneBox($elsewhere, $other);

    $names = fn ($component): array => $component->viewData('products')->pluck('name')->all();

    expect($names(Livewire::actingAs($warehouse)->test(Stock::class)))->toBe(['POLLO ENTERO']);

    $component = Livewire::actingAs(User::factory()->superAdmin()->create())->test(Stock::class);

    expect($names($component))->toBe(['ALAS DE POLLO', 'POLLO ENTERO']);

    $component->set('productId', $elsewhere->id)->set('chamberId', $mine->id)->assertSet('productId', null);

    expect($names($component))->toBe(['POLLO ENTERO']);
});
