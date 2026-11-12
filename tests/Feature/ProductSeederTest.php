<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Chamber;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\RegisterEntry;
use Database\Seeders\ProductSeeder;

it('loads every product with a short unique code, its category and the previous code', function () {
    $this->seed(ProductSeeder::class);
    $this->seed(ProductSeeder::class);

    $codes = array_column(ProductSeeder::PRODUCTS, 1);

    expect($codes)->toHaveCount(count(array_unique($codes)))
        ->and(Product::query()->count())->toBe(count(ProductSeeder::PRODUCTS))
        ->and(Product::query()->where('is_active', false)->count())->toBe(0);

    foreach (ProductSeeder::PRODUCTS as [$name, $code, $category, $previous]) {
        expect($code)->toMatch('/^[A-Z0-9]{2,12}$/')
            ->and($code)->not->toBe(strtoupper($name))
            ->and($previous)->not->toBe($code);
    }

    $byCategory = Product::query()->with('category')->get()->groupBy(fn (Product $product): string => $product->category->name)->map->count();
    expect($byCategory->keys()->sort()->values()->all())->toBe(['CARNE', 'MARISCO', 'PAPAS', 'POLLO', 'PRODUCTO BACHOCO']);

    $tender = Product::query()->where('code', 'TENEMP')->firstOrFail();
    expect($tender->name)->toBe('TENDER EMP')
        ->and($tender->description)->toBe('Código anterior: TEN08DER12EMP000')
        ->and(Product::query()->where('code', 'POLNAV')->first()->name)->toBe('POLLO NAVIDEÑO');
});

it('renames products loaded with the previous code, except those that already have boxes', function () {
    $category = Category::factory()->create(['name' => 'PRODUCTO BACHOCO']);
    $free = Product::factory()->create(['category_id' => $category->id, 'code' => 'TEN08DER12EMP000', 'name' => 'TENDER EMP']);
    $used = Product::factory()->create(['category_id' => $category->id, 'code' => 'NUG08EMP12MP000', 'name' => 'NUGGET MP']);

    $branch = Branch::factory()->create();
    $chamber = Chamber::factory()->for($branch)->create();
    $user = User::factory()->superAdmin()->create();
    app(RegisterEntry::class)->handle($user, $chamber, [
        ['product_id' => $used->id, 'boxes_count' => 1, 'weight_per_box_kg' => '10', 'price_per_kg_mxn' => '0.50'],
    ]);

    $this->seed(ProductSeeder::class);

    expect($free->fresh()->code)->toBe('TENEMP')
        ->and($free->fresh()->description)->toBe('Código anterior: TEN08DER12EMP000')
        ->and($used->fresh()->code)->toBe('NUG08EMP12MP000')
        ->and(Product::query()->where('code', 'NUGMP')->exists())->toBeFalse();
});

it('gives the two products with the same name different codes', function () {
    $this->seed(ProductSeeder::class);

    expect(Product::query()->where('name', 'SUADERO')->orderBy('id')->pluck('code')->all())->toBe(['SUADE', 'SUADE2']);
});
