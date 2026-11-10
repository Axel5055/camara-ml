<?php

use App\Livewire\Admin\Categories;
use App\Livewire\Admin\Products;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

function warehouseUser(): User
{
    return User::factory()->warehouse(Branch::factory()->create())->create();
}

it('lets a warehouse user open the products page and shows them the menu entry', function () {
    $user = warehouseUser();

    $this->actingAs($user)->get(route('admin.products'))
        ->assertOk()
        ->assertSeeHtml('data-test="product-create"')
        ->assertDontSee('wire:click="edit(', false)
        ->assertDontSee('wire:click="toggle(', false);

    $this->get(route('dashboard'))->assertSeeHtml('data-test="nav-products"');
});

it('lets a warehouse user register a new product, always active, and leaves an audit trail', function () {
    $category = Category::factory()->create();
    $user = warehouseUser();

    Livewire::actingAs($user)->test(Products::class)
        ->call('create')
        ->set('category_id', $category->id)
        ->set('code', 'pol01')
        ->set('name', 'Pollo nuevo')
        ->assertSet('code', 'POLLONUEV')
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::query()->where('code', 'POLLONUEV')->sole();

    expect($product->is_active)->toBeTrue()
        ->and($product->category_id)->toBe($category->id)
        ->and(AuditLog::query()->where('user_id', $user->id)->exists())->toBeTrue();
});

it('suggests the code from the name, ignores what the client sends and avoids repeated codes', function () {
    Product::factory()->create(['code' => 'REPE']);
    $category = Category::factory()->create();

    $component = Livewire::actingAs(warehouseUser())->test(Products::class)
        ->call('create')
        ->assertSeeHtml('data-test="product-code"')
        ->set('name', 'Tilapia Ámatista 10 kg')
        ->assertSet('code', 'TILAPIAAM')
        ->set('name', 'Repe')
        ->assertSet('code', 'REPE2')
        ->set('category_id', $category->id)
        ->set('code', 'HACK')
        ->call('save')
        ->assertHasNoErrors();

    expect(Product::query()->where('code', 'REPE2')->exists())->toBeTrue()
        ->and(Product::query()->where('code', 'HACK')->exists())->toBeFalse();
});

it('always saves the name of products and categories in capital letters', function () {
    $category = Category::factory()->create();
    $super = User::factory()->superAdmin()->create();

    Livewire::actingAs($super)->test(Products::class)
        ->call('create')
        ->set('category_id', $category->id)
        ->set('name', '  pechuga de Pollo ñandú ')
        ->call('save')
        ->assertHasNoErrors();

    expect(Product::query()->where('code', 'PECHUGADE')->sole()->name)->toBe('PECHUGA DE POLLO ÑANDÚ');

    Livewire::actingAs($super)->test(Categories::class)
        ->call('create')
        ->set('name', 'Pescados y mariscos')
        ->call('save')
        ->assertHasNoErrors();

    expect(Category::query()->where('name', 'PESCADOS Y MARISCOS')->exists())->toBeTrue();
});

it('asks for a name with letters when the code cannot be generated', function () {
    Livewire::actingAs(warehouseUser())->test(Products::class)
        ->call('create')
        ->set('category_id', Category::factory()->create()->id)
        ->set('name', '1234')
        ->assertSet('code', '')
        ->call('save')
        ->assertHasErrors('code');
});

it('does not let a warehouse user edit or deactivate products', function () {
    $product = Product::factory()->create(['name' => 'Original']);

    Livewire::actingAs(warehouseUser())->test(Products::class)->call('edit', $product->id)->assertForbidden();
    Livewire::actingAs(warehouseUser())->test(Products::class)->call('toggle', $product->id)->assertForbidden();

    expect($product->fresh()->is_active)->toBeTrue();
});

it('keeps the read-only administrator from creating products', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test(Products::class)->call('create')->assertForbidden();
    $this->actingAs($admin)->get(route('admin.products'))->assertOk()->assertDontSeeHtml('data-test="product-create"');
    $this->get(route('dashboard'))->assertDontSeeHtml('data-test="nav-products"');
});

it('keeps the super administrator in control of the products', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.products'))
        ->assertOk()
        ->assertSeeHtml('data-test="product-create"');
});
