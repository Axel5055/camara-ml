<?php

namespace App\Livewire\Inventory;

use App\Models\Branch;
use App\Models\Chamber;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;

/**
 * Chamber-to-chamber transfer: same capture as an exit, but the boxes travel (in transit) until the destination receives them.
 */
#[Title('Nueva transferencia')]
class CreateTransfer extends CreateExit
{
    public ?int $destChamberId = null;

    protected function destinationRules(): array
    {
        return ['destChamberId' => ['required', 'exists:chambers,id']];
    }

    protected function destination(): array
    {
        $chamber = Chamber::query()->with('branch')->findOrFail($this->destChamberId);

        return [Branch::query()->findOrFail($chamber->branch_id), $chamber];
    }

    protected function isTransfer(): bool
    {
        return true;
    }

    protected function destinationViewData(): array
    {
        return [
            'destChambers' => Chamber::query()->with('branch:id,name')
                ->where('is_active', true)
                ->whereHas('branch', fn (Builder $query) => $query->where('is_active', true))
                ->when($this->originChamberId, fn (Builder $query, int $id) => $query->where('id', '!=', $id))
                ->orderBy('branch_id')->orderBy('name')->get(),
        ];
    }
}
