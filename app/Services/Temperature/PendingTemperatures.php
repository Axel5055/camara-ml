<?php

namespace App\Services\Temperature;

use App\Enums\TemperatureShift;
use App\Enums\UserRole;
use App\Models\Chamber;
use App\Models\TemperatureReading;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * What a warehouse user has left to record today, among the active chambers assigned to them (not those they may
 * only ask for product).
 */
class PendingTemperatures
{
    /**
     * Chambers with no reading at all today, whoever takes it. While there is any, the user cannot use the system.
     *
     * @return Collection<int, Chamber>
     */
    public function for(User $user): Collection
    {
        if (! config('temperature.enforce') || ! $this->records($user)) {
            return new Collection;
        }

        return $this->chambers($user)
            ->whereNotIn('id', $this->todaysReadings()->select('chamber_id'))
            ->get();
    }

    /**
     * Chambers whose optional afternoon reading can be recorded now: they have today's morning one only. It never
     * blocks anything.
     *
     * @return Collection<int, Chamber>
     */
    public function afternoonFor(User $user): Collection
    {
        if (! $this->records($user) || now()->format('H:i') < config('temperature.afternoon_opens')) {
            return new Collection;
        }

        return $this->chambers($user)
            ->whereIn('id', $this->todaysReadings()->where('shift', TemperatureShift::Morning)->select('chamber_id'))
            ->whereNotIn('id', $this->todaysReadings()->where('shift', TemperatureShift::Afternoon)->select('chamber_id'))
            ->get();
    }

    private function records(User $user): bool
    {
        return $user->is_active && $user->role === UserRole::Warehouse;
    }

    /**
     * @return Builder<Chamber>
     */
    private function chambers(User $user): Builder
    {
        return Chamber::query()
            ->accessibleBy($user)
            ->where('is_active', true)
            ->with('branch:id,name')
            ->orderBy('name');
    }

    /**
     * @return Builder<TemperatureReading>
     */
    private function todaysReadings(): Builder
    {
        return TemperatureReading::query()->where('reading_date', now()->toDateString());
    }
}
