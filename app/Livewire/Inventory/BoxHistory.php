<?php

namespace App\Livewire\Inventory;

use App\Enums\BoxStatus;
use App\Livewire\Concerns\InteractsWithChambers;
use App\Livewire\Concerns\ReportsInventoryErrors;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Chamber;
use App\Services\Inventory\WithdrawPartial;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Historial de caja')]
class BoxHistory extends Component
{
    use InteractsWithChambers, ReportsInventoryErrors;

    public Box $box;

    public string $weightOut = '';

    public ?int $destBranchId = null;

    public string $requester = '';

    public string $notes = '';

    public function mount(Box $box): void
    {
        Gate::authorize('view', $box);

        $this->box = $box;
    }

    public function withdraw(WithdrawPartial $withdrawPartial): void
    {
        $this->validate([
            'weightOut' => ['required', 'numeric', 'gt:0'],
            'destBranchId' => ['required', 'exists:branches,id'],
            'requester' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $done = $this->attempt(fn () => $withdrawPartial->handle(
            Auth::user(),
            $this->box->refresh(),
            $this->weightOut,
            Branch::query()->findOrFail($this->destBranchId),
            $this->requester,
            notes: $this->notes ?: null,
        ));

        if ($done) {
            $this->reset('weightOut', 'notes');
            $this->box->refresh();
            Flux::toast(text: 'Retiro parcial registrado.', variant: 'success');
        }
    }

    public function render(): View
    {
        $movements = $this->box->movements()->with('user:id,name')->get();
        $chamberIds = $movements->pluck('from_chamber_id')->merge($movements->pluck('to_chamber_id'))->filter()->unique();
        $branchIds = $movements->pluck('to_branch_id')->filter()->unique();

        $currentChamber = $this->box->current_chamber_id ? Chamber::query()->find($this->box->current_chamber_id) : null;

        return view('livewire.inventory.box-history', [
            'movements' => $movements,
            'chamberNames' => Chamber::query()->whereIn('id', $chamberIds)->pluck('name', 'id'),
            'branchNames' => Branch::query()->whereIn('id', $branchIds)->pluck('name', 'id'),
            'product' => $this->box->product()->first(),
            'canWithdraw' => $this->box->status === BoxStatus::Available
                && $currentChamber !== null
                && Auth::user()->canAccessChamber($currentChamber),
            'branches' => $this->activeBranches(),
            'showCosts' => Gate::allows('view-costs'),
        ]);
    }
}
