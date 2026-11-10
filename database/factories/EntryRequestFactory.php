<?php

namespace Database\Factories;

use App\Enums\EntryRequestStatus;
use App\Models\Chamber;
use App\Models\EntryRequest;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EntryRequest>
 */
class EntryRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chamber_id' => Chamber::factory(),
            'user_id' => User::factory(),
            'occurred_at' => now(),
            'total_boxes' => 2,
            'total_weight_kg' => '20.000',
            'total_cost_mxn' => '100.00',
            'notes' => null,
            'groups' => fn () => [[
                'product_id' => Product::factory()->create()->id,
                'boxes_count' => 2,
                'weight_per_box_kg' => '10.000',
                'unit_cost_mxn' => '50.00',
                'manual_barcodes' => [],
                'manual_barcode_origin' => null,
            ]],
            'status' => EntryRequestStatus::Pending,
            'client_token' => (string) Str::uuid(),
        ];
    }
}
