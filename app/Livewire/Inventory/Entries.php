<?php

namespace App\Livewire\Inventory;

use App\Enums\DocumentStatus;
use App\Livewire\Concerns\InteractsWithChambers;
use App\Models\Entry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Entradas')]
class Entries extends Component
{
    use InteractsWithChambers, WithPagination;

    public ?int $chamberId = null;

    public string $search = '';

    public ?string $from = null;

    public ?string $to = null;

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('chamberId', 'search', 'from', 'to');
        $this->resetPage();
    }

    /**
     * Entries the user may see, narrowed by the active filters.
     *
     * @return Builder<Entry>
     */
    private function filteredEntries(): Builder
    {
        $visible = $this->visibleChamberIds();

        return Entry::query()
            ->when($visible !== null, fn (Builder $query) => $query->whereIn('chamber_id', $visible))
            ->when($this->chamberId, fn (Builder $query) => $query->where('chamber_id', $this->chamberId))
            ->when(trim($this->search) !== '', fn (Builder $query) => $query->where('reference', 'like', '%'.trim($this->search).'%'))
            ->when($this->from, fn (Builder $query, string $from) => $query->where('occurred_at', '>=', $from.' 00:00:00'))
            ->when($this->to, fn (Builder $query, string $to) => $query->where('occurred_at', '<=', $to.' 23:59:59'));
    }

    public function render(): View
    {
        $showCosts = Gate::allows('view-costs');

        $columns = ['id', 'reference', 'chamber_id', 'user_id', 'occurred_at', 'total_boxes', 'total_weight_kg', 'status'];

        $summary = $this->filteredEntries()->toBase()
            ->where('status', DocumentStatus::Confirmed)
            ->selectRaw('count(*) as entries, coalesce(sum(total_boxes), 0) as boxes, coalesce(sum(total_weight_kg), 0) as kg'.($showCosts ? ', coalesce(sum(total_cost_mxn), 0) as cost' : ''))
            ->first();

        return view('livewire.inventory.entries', [
            'entries' => $this->filteredEntries()
                ->select($showCosts ? [...$columns, 'total_cost_mxn'] : $columns)
                ->withMin('items as min_unit_cost', 'price_per_kg_mxn')
                ->withMax('items as max_unit_cost', 'price_per_kg_mxn')
                ->with(['chamber:id,name,branch_id', 'chamber.branch:id,name', 'user:id,name'])
                ->orderByDesc('occurred_at')->orderByDesc('id')
                ->paginate(20),
            'summary' => $summary,
            'chambers' => $this->accessibleChambers(),
            'showCosts' => $showCosts,
            'hasFilters' => $this->chamberId || trim($this->search) !== '' || $this->from || $this->to,
        ]);
    }
}
