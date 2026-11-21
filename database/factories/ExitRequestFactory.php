<?php

namespace Database\Factories;

use App\Enums\ExitRequestStatus;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\ExitRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExitRequest>
 */
class ExitRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'chamber_id' => Chamber::factory(),
            'user_id' => fn (array $attributes) => User::factory()->requester(Branch::query()->whereKey($attributes['branch_id'])->firstOrFail()),
            'total_boxes_requested' => 2,
            'notes' => null,
            'status' => ExitRequestStatus::Pending,
            'client_token' => (string) Str::uuid(),
        ];
    }
}
