<?php

use App\Models\Category;
use Database\Seeders\CategorySeeder;

it('creates the business product categories and can run again without duplicating them', function () {
    $this->seed(CategorySeeder::class);
    $this->seed(CategorySeeder::class);

    expect(Category::query()->orderBy('id')->pluck('name')->all())
        ->toBe(['PRODUCTO BACHOCO', 'CARNE', 'MARISCO', 'PAPAS', 'POLLO', 'CAJAS NEGRAS'])
        ->and(Category::query()->where('is_active', false)->count())->toBe(0);
});
