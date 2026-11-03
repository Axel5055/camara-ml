<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Product categories of the business. Safe to run more than once: existing ones are left untouched.
     *
     * @var list<string>
     */
    public const NAMES = ['PRODUCTO BACHOCO', 'CARNE', 'MARISCO', 'PAPAS', 'POLLO', 'CAJAS NEGRAS'];

    public function run(): void
    {
        foreach (self::NAMES as $name) {
            Category::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
