<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Chamber;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a local development environment: one administrator, one branch with a chamber and its warehouse user.
     * The password of both users is "password"; change it before using real data.
     */
    public function run(): void
    {
        User::factory()->superAdmin()->create([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
        ]);

        $branch = Branch::factory()->create(['code' => 'CENTRO', 'name' => 'Sucursal Centro']);
        Branch::factory()->create(['code' => 'SUR', 'name' => 'Sucursal Sur']);
        $chamber = Chamber::factory()->for($branch)->create(['code' => 'CENTRO-1', 'name' => 'Cámara 1']);

        $almacenista = User::factory()->warehouse($branch)->create([
            'name' => 'Almacenista',
            'email' => 'almacen@example.com',
        ]);
        $almacenista->chambers()->attach($chamber);

        $this->call(ProductSeeder::class);
    }
}
