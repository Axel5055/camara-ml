<?php

namespace App\Livewire\Inventory;

use App\Enums\DocumentStatus;
use App\Livewire\Concerns\InteractsWithChambers;
use App\Livewire\Concerns\ReportsInventoryErrors;
use App\Models\StockExit;
use App\Services\Inventory\ReviewExitRequest;
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

    /** direct (registered by the warehouse user) | requested (authorized from a branch request) */
    public string $source = 'direct';

    /** Exit whose request the warehouse user is about to confirm. */
    public ?int $confirmingId = null;

    public ?string $from = null;

    public ?string $to = null;

    public function mount(string $source = 'direct'): void
    {
        $this->source = $source === 'requested' ? 'requested' : 'direct';
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['chamberId', 'search', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function askToConfirm(int $id): void
    {
        $exit = $this->filteredExits()->with('exitRequest.chamber')->findOrFail($id);
        Gate::authorize('validate', $exit->exitRequest ?? abort(404));

        $this->resetErrorBag('operation');
        $this->confirmingId = $exit->id;
        Flux::modal('confirm-requested-exit')->show();
    }

    /**
     * Confirming moves the exit from "Por solicitud" to the direct exits; nothing else changes.
     */
    public function confirmRequested(ReviewExitRequest $review): void
    {
        $exit = $this->filteredExits()->with('exitRequest.chamber')->findOrFail($this->confirmingId);
        $request = $exit->exitRequest ?? abort(404);
        Gate::authorize('validate', $request);

        if ($this->attempt(fn () => $review->validate(Auth::user(), $request))) {
            $this->confirmingId = null;
            Flux::modal('confirm-requested-exit')->close();
            Flux::toast(text: 'Salida confirmada: ahora está en Salidas directas.', variant: 'success');
        }
    }

    public function clearFilters(): void
    {
        $this->reset('chamberId', 'search', 'from', 'to');
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
            ->whereNull('dest_chamber_id')
            ->when($this->source === 'requested', fn (Builder $query) => $query->whereHas('exitRequest', fn (Builder $request) => $request->whereNull('validated_at')), fn (Builder $query) => $query->whereDoesntHave('exitRequest', fn (Builder $request) => $request->whereNull('validated_at')))
            ->when($this->from, fn (Builder $query, string $from) => $query->where('occurred_at', '>=', $from.' 00:00:00'))
            ->when($this->to, fn (Builder $query, string $to) => $query->where('occurred_at', '<=', $to.' 23:59:59'));
    }

    public function render(): View
    {
        $showCosts = Gate::allows('view-costs');
        $columns = ['id', 'reference', 'requester_name', 'origin_chamber_id', 'dest_branch_id', 'dest_chamber_id', 'delivery_chamber_id', 'user_id', 'occurred_at', 'total_boxes', 'total_weight_kg', 'status'];

        $summary = $this->filteredExits()->toBase()
            ->where('status', DocumentStatus::Confirmed)
            ->selectRaw('count(*) as exits, coalesce(sum(total_boxes), 0) as boxes, coalesce(sum(total_weight_kg), 0) as kg'.($showCosts ? ', coalesce(sum(total_cost_mxn), 0) as cost' : ''))
            ->first();

        return view('livewire.inventory.exits', [
            'exits' => $this->filteredExits()
                ->select($showCosts ? [...$columns, 'total_cost_mxn'] : $columns)
                ->withMin(['items as min_unit_cost' => fn (Builder $query) => $query->where('is_active', true)], 'unit_cost_snapshot_mxn')
                ->withMax(['items as max_unit_cost' => fn (Builder $query) => $query->where('is_active', true)], 'unit_cost_snapshot_mxn')
                ->with(['originChamber:id,name', 'destBranch:id,name', 'destChamber:id,name', 'deliveryChamber:id,name', 'user:id,name', 'transfer:id,exit_id,status', 'exitRequest:id,exit_id,chamber_id,status,validated_at'])
                ->orderByDesc('occurred_at')->orderByDesc('id')
                ->paginate(20),
            'summary' => $summary,
            'chambers' => $this->accessibleChambers(),
            'showCosts' => $showCosts,
            'isRequested' => $this->source === 'requested',
            'confirming' => $this->confirmingId ? StockExit::query()->select('id', 'reference', 'total_boxes', 'total_weight_kg')->find($this->confirmingId) : null,
            'hasFilters' => $this->chamberId || trim($this->search) !== '' || $this->from || $this->to,
        ])->title($this->source === 'requested' ? 'Salidas por solicitud' : 'Salidas');
    }
}
