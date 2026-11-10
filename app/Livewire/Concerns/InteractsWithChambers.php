<?php

namespace App\Livewire\Concerns;

use App\Models\Branch;
use App\Models\Chamber;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

trait InteractsWithChambers
{
    /**
     * Active chambers the signed-in user may work with.
     *
     * @return Collection<int, Chamber>
     */
    protected function accessibleChambers(): Collection
    {
        return Chamber::query()
            ->accessibleBy(Auth::user())
            ->where('is_active', true)
            ->with('branch')
            ->orderBy('name')
            ->get();
    }

    /**
     * The chamber to preselect in a form: the only one assigned to the user. With several (or all of them, as
     * administrators have) the user chooses.
     */
    protected function onlyChamberId(): ?int
    {
        if (Auth::user()->seesAllChambers()) {
            return null;
        }

        $chambers = $this->accessibleChambers();

        return $chambers->count() === 1 ? $chambers->first()->id : null;
    }

    /**
     * Ids of every chamber the user may see, or null when they may see all of them.
     *
     * @return array<int, int>|null
     */
    protected function visibleChamberIds(): ?array
    {
        $user = Auth::user();

        return $user->seesAllChambers()
            ? null
            : Chamber::query()->accessibleBy($user)->pluck('id')->all();
    }

    /**
     * @return Collection<int, Branch>
     */
    protected function activeBranches(): Collection
    {
        return Branch::query()->where('is_active', true)->orderBy('name')->get();
    }
}
