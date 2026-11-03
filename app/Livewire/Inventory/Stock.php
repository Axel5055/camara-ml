<?php

namespace App\Livewire\Inventory;

use App\Enums\BoxStatus;
use App\Livewire\Concerns\InteractsWithChambers;
use App\Models\Box;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Existencias')]
class Stock extends Component
{
    use InteractsWithChambers, WithPagination;

    public ?int $chamberId = null;

    public ?int $categoryId = null;

    public ?int $productId = null;

    /** available | partial | in_transit | dispatched | depleted | written_off | all */
    public string $status = 'available';

    public string $search = '';

    public function updated(string $property): void
    {
        if ($property === 'categoryId') {
            $this->productId = null;
        }

        $this->resetPage();
    }

    /**
     * Boxes the user may see, narrowed by the active filters.
     *
     * @return Builder<Box>
     */
    private function filteredBoxes(): Builder
    {
        $visible = $this->visibleChamberIds();

        return Box::query()
            ->when($visible !== null, fn (Builder $query) => $query->whereIn('current_chamber_id', $visible))
            ->when($this->chamberId, fn (Builder $query) => $query->where('current_chamber_id', $this->chamberId))
            ->when($this->categoryId, fn (Builder $query) => $query->where('category_id', $this->categoryId))
            ->when($this->productId, fn (Builder $query) => $query->where('product_id', $this->productId))
            ->when(trim($this->search) !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('barcode', 'like', '%'.strtoupper(trim($this->search)).'%')
                ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', '%'.trim($this->search).'%'))))
            ->when($this->status === 'partial', fn (Builder $query) => $query
                ->where('status', BoxStatus::Available)->whereColumn('current_weight_kg', '<', 'initial_weight_kg'))
            ->when(BoxStatus::tryFrom($this->status), fn (Builder $query, BoxStatus $status) => $query->where('status', $status));
    }

    public function render(): View
    {
        $columns = ['id', 'barcode', 'product_id', 'category_id', 'initial_weight_kg', 'current_weight_kg', 'unit_cost_mxn', 'entered_at', 'status', 'current_chamber_id'];

        $summary = $this->filteredBoxes()->toBase()
            ->selectRaw('count(*) as boxes, coalesce(sum(current_weight_kg), 0) as kg, coalesce(sum(unit_cost_mxn * current_weight_kg / initial_weight_kg), 0) as value')
            ->first();

        return view('livewire.inventory.stock', [
            'boxes' => $this->filteredBoxes()
                ->select($columns)
                ->with(['product:id,name,code', 'category:id,name', 'currentChamber:id,name,branch_id', 'currentChamber.branch:id,name'])
                ->orderByDesc('entered_at')
                ->orderBy('id')
                ->paginate(25),
            'summary' => $summary,
            'chambers' => $this->accessibleChambers(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()
                ->when($this->categoryId, fn (Builder $query) => $query->where('category_id', $this->categoryId))
                ->orderBy('name')->get(['id', 'name', 'code']),
            'canSeeAllStatuses' => Auth::user()->seesAllChambers(),
        ]);
    }
}
