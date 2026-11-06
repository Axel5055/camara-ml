<?php

namespace App\Livewire\Inventory;

use App\Enums\DocumentStatus;
use App\Livewire\Concerns\InteractsWithChambers;
use App\Livewire\Concerns\ReportsInventoryErrors;
use App\Models\StockExit;
use App\Services\Inventory\ReverseExit;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Salidas')]
class Exits extends Component
{
    use InteractsWithChambers, ReportsInventoryErrors, WithPagination;

    public ?int $chamberId = null;

    public string $search = '';

    /** all | delivery (to a branch) | transfer (to another chamber) */
    public string $type = 'all';

    public ?string $from = null;

    public ?string $to = null;

    public ?int $reversingId = null;

    public string $reason = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['chamberId', 'search', 'type', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('chamberId', 'search', 'type', 'from', 'to');
        $this->resetPage();
    }

    /**
     * Exits the user may see, narrowed by the active filters.
     *
     * @return Builder<StockExit>
     */
    private function filteredExits(): Builder
    {
        $visible = $this->visibleChamberIds();
        $search = trim($this->search);

        return StockExit::query()
            ->when($visible !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereIn('origin_chamber_id', $visible)->orWhereIn('dest_chamber_id', $visible)))
            ->when($this->chamberId, fn (Builder $query) => $query->where('origin_chamber_id', $this->chamberId))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('reference', 'like', "%{$search}%")->orWhere('requester_name', 'like', "%{$search}%")))
            ->when($this->type === 'delivery', fn (Builder $query) => $query->whereNull('dest_chamber_id'))
            ->when($this->type === 'transfer', fn (Builder $query) => $query->whereNotNull('dest_chamber_id'))
            ->when($this->from, fn (Builder $query, string $from) => $query->where('occurred_at', '>=', $from.' 00:00:00'))
            ->when($this->to, fn (Builder $query, string $to) => $query->where('occurred_at', '<=', $to.' 23:59:59'));
    }

    public function askReverse(int $id): void
    {
        Gate::authorize('reverse', StockExit::query()->findOrFail($id));

        $this->reversingId = $id;
        $this->reason = '';
        $this->resetErrorBag();
        Flux::modal('reverse-exit')->show();
    }

    public function reverse(ReverseExit $reverseExit): void
    {
        $exit = StockExit::query()->findOrFail($this->reversingId);
        Gate::authorize('reverse', $exit);

        $done = $this->attempt(fn () => $reverseExit->handle(Auth::user(), $exit, $this->reason));

        if ($done) {
            Flux::modal('reverse-exit')->close();
            Flux::toast(text: 'Salida revertida.', variant: 'success');
        }
    }

    public function render(): View
    {
        $showCosts = Gate::allows('view-costs');
        $columns = ['id', 'reference', 'requester_name', 'origin_chamber_id', 'dest_branch_id', 'dest_chamber_id', 'user_id', 'occurred_at', 'total_boxes', 'total_weight_kg', 'status'];

        $summary = $this->filteredExits()->toBase()
            ->where('status', DocumentStatus::Confirmed)
            ->selectRaw('count(*) as exits, coalesce(sum(total_boxes), 0) as boxes, coalesce(sum(total_weight_kg), 0) as kg'.($showCosts ? ', coalesce(sum(total_cost_mxn), 0) as cost' : ''))
            ->first();

        return view('livewire.inventory.exits', [
            'exits' => $this->filteredExits()
                ->select($showCosts ? [...$columns, 'total_cost_mxn'] : $columns)
                ->withMin(['items as min_unit_cost' => fn (Builder $query) => $query->where('is_active', true)], 'unit_cost_snapshot_mxn')
                ->withMax(['items as max_unit_cost' => fn (Builder $query) => $query->where('is_active', true)], 'unit_cost_snapshot_mxn')
                ->with(['originChamber:id,name', 'destBranch:id,name', 'destChamber:id,name', 'user:id,name', 'transfer:id,exit_id,status'])
                ->orderByDesc('occurred_at')->orderByDesc('id')
                ->paginate(20),
            'summary' => $summary,
            'chambers' => $this->accessibleChambers(),
            'showCosts' => $showCosts,
            'isSuperAdmin' => Auth::user()->isSuperAdmin(),
            'hasFilters' => $this->chamberId || trim($this->search) !== '' || $this->type !== 'all' || $this->from || $this->to,
        ]);
    }
}
