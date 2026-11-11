<?php

use App\Livewire\Admin\Products;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\EntryRequest;
use App\Models\Product;
use App\Models\StockThreshold;
use App\Models\User;
use App\Services\Inventory\RegisterEntry;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('lets a super administrator delete a product that has no movements and audits it', function () {
    $product = Product::factory()->create(['code' => 'BORRAME', 'name' => 'BORRAME']);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get(route('admin.products'))->assertSeeHtml('data-test="product-delete-'.$product->id.'"');

    Livewire::actingAs($admin)->test(Products::class)
        ->call('confirmDelete', $product->id)
        ->assertSet('deletingId', $product->id)
        ->call('delete')
        ->assertHasNoErrors();

    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse()
        ->and(DB::table('audit_logs')->where('event', 'product.deleted')->where('auditable_id', $product->id)->exists())->toBeTrue();
});

it('removes the low stock thresholds that were set for the deleted product', function () {
    $branch = Branch::factory()->create();
    $chamber = Chamber::factory()->for($branch)->create();
    $product = Product::factory()->create();
    StockThreshold::query()->create(['chamber_id' => $chamber->id, 'product_id' => $product->id, 'min_boxes' => 5]);

    Livewire::actingAs(User::factory()->superAdmin()->create())->test(Products::class)
        ->call('confirmDelete', $product->id)
        ->call('delete');

    expect(Product::query()->count())->toBe(0)->and(StockThreshold::query()->count())->toBe(0);
});

it('never deletes a product that already has movements', function () {
    $branch = Branch::factory()->create();
    $chamber = Chamber::factory()->for($branch)->create();
    $worker = User::factory()->warehouse($branch)->create();
    $worker->chambers()->attach($chamber);
    $product = Product::factory()->create();
    app(RegisterEntry::class)->handle($worker, $chamber, [
        ['product_id' => $product->id, 'boxes_count' => 1, 'weight_per_box_kg' => '10', 'unit_cost_mxn' => '50'],
    ]);

    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get(route('admin.products'))->assertDontSeeHtml('data-test="product-delete-'.$product->id.'"');

    Livewire::actingAs($admin)->test(Products::class)
        ->set('deletingId', $product->id)
        ->call('delete');

    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue();
});

it('does not delete a product that is part of a pending entry request', function () {
    $product = Product::factory()->create();
    EntryRequest::factory()->create(['groups' => [
        ['product_id' => $product->id, 'boxes_count' => 1, 'weight_per_box_kg' => '10', 'unit_cost_mxn' => '50', 'manual_barcodes' => [], 'manual_barcode_origin' => null],
    ]]);

    Livewire::actingAs(User::factory()->superAdmin()->create())->test(Products::class)
        ->set('deletingId', $product->id)
        ->call('delete');

    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue();
});

it('keeps the delete option away from every other role', function () {
    $product = Product::factory()->create();
    $branch = Branch::factory()->create();

    foreach ([User::factory()->warehouse($branch)->create(), User::factory()->create(['role' => 'admin']), User::factory()->create(['role' => 'manager'])] as $user) {
        $this->actingAs($user)->get(route('admin.products'))->assertDontSeeHtml('data-test="product-delete-'.$product->id.'"');

        Livewire::actingAs($user)->test(Products::class)->call('confirmDelete', $product->id)->assertForbidden();
    }

    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue();
});
