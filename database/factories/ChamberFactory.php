<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Chamber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chamber>
 */
class ChamberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'code' => strtoupper(fake()->unique()->lexify('CAM-????')),
            'name' => 'Cámara '.fake()->unique()->numberBetween(1, 99999),
            'is_active' => true,
        ];
    }
}
