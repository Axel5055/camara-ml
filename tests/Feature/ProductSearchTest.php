<?php

use App\Livewire\Inventory\Stock;
use App\Livewire\Reports\EntriesReport;
use App\Livewire\Reports\LowStock;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

it('uses the product search box instead of a select in stock and the reports', function () {
    $product = Product::factory()->create(['name' => 'POLLO ENTERO', 'code' => 'POLLO']);
    $this->actingAs(User::factory()->superAdmin()->create());

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
