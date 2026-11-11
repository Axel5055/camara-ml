<?php

namespace App\Livewire\Inventory;

use App\Enums\BoxStatus;
use App\Livewire\Concerns\InteractsWithChambers;
use App\Livewire\Concerns\ReportsInventoryErrors;
use App\Models\Box;
use App\Models\Category;
use App\Models\Chamber;
use App\Models\Product;
use App\Services\Inventory\AdjustStockSummary;
use App\Services\Inventory\EditBox;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use LogicException;
use stdClass;

#[Title('Existencias')]
class Stock extends Component
{
    use InteractsWithChambers, ReportsInventoryErrors, WithPagination;

    public ?int $chamberId = null;

    public ?int $categoryId = null;

    public ?int $productId = null;

    /** available | partial | in_transit | dispatched | depleted | written_off | all */
    public string $status = 'available';

    public string $search = '';

    /** boxes | summary */
    #[Url(as: 'vista')]
    public string $view = 'boxes';

    public ?int $adjustChamberId = null;

    public ?int $adjustProductId = null;

    public string $adjustBoxes = '';

    public string $adjustWeight = '';

    public string $adjustCost = '';

    public string $adjustReason = '';

    /** @var array{boxes: string, weight: string, cost: string} */
    public array $adjustOriginal = ['boxes' => '', 'weight' => '', 'cost' => ''];

    public ?int $editBoxId = null;

    public string $editWeight = '';

    public string $editCost = '';

    public string $editReason = '';

    /** @var array{weight: string, cost: string} */
    public array $editOriginal = ['weight' => '', 'cost' => ''];

    public function updated(string $property): void
    {
        if ($property === 'categoryId') {
            $this->productId = null;
        }

        $this->resetPage();
    }

    public function showView(string $view): void
    {
        $this->view = $view === 'summary' ? 'summary' : 'boxes';
        $this->resetPage();
    }

    /**
     * Boxes the user may see, narrowed by the chamber, category, product and search filters (not by status).
     *
     * @return Builder<Box>
     */
    private function visibleBoxes(): Builder
    {
        $visible = $this->visibleChamberIds();

        return Box::query()
            ->when($visible !== null, fn (Builder $query) => $query->whereIn('current_chamber_id', $visible))
            ->when($this->chamberId, fn (Builder $query) => $query->where('current_chamber_id', $this->chamberId))
            ->when($this->categoryId, fn (Builder $query) => $query->where('category_id', $this->categoryId))
            ->when($this->productId, fn (Builder $query) => $query->where('product_id', $this->productId))
            ->when(trim($this->search) !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('barcode', 'like', '%'.strtoupper(trim($this->search)).'%')
                ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', '%'.trim($this->search).'%'))));
    }

    /**
     * Boxes the user may see, narrowed by every active filter.
     *
     * @return Builder<Box>
     */
    private function filteredBoxes(): Builder
    {
        return $this->visibleBoxes()
            ->when($this->status === 'partial', fn (Builder $query) => $query
                ->where('status', BoxStatus::Available)->whereColumn('current_weight_kg', '<', 'initial_weight_kg'))
            ->when(BoxStatus::tryFrom($this->status), fn (Builder $query, BoxStatus $status) => $query->where('status', $status));
    }

    /**
     * Available stock grouped by chamber and product, one block per chamber.
     *
     * @return Collection<int, array{chamber: Chamber, rows: Collection<int, stdClass>, boxes: int, kg: float, value: float}>
     */
    private function chamberSummaries(): Collection
    {
        $rows = $this->visibleBoxes()->toBase()
            ->where('status', BoxStatus::Available->value)
            ->groupBy('current_chamber_id', 'product_id')
            ->selectRaw('current_chamber_id as chamber_id, product_id, count(*) as boxes, sum(current_weight_kg) as kg, sum(unit_cost_mxn * current_weight_kg / initial_weight_kg) as value, avg(unit_cost_mxn) as avg_cost, sum(current_weight_kg < initial_weight_kg) as partial_boxes')
            ->get();

        $products = Product::query()->whereIn('id', $rows->pluck('product_id'))->get(['id', 'name', 'code'])->keyBy('id');
        $chambers = Chamber::query()->with('branch:id,name')->whereIn('id', $rows->pluck('chamber_id'))->get()->keyBy('id');

        return $rows->groupBy('chamber_id')->map(function (Collection $group, int|string $chamberId) use ($products, $chambers): array {
            $group = $group->each(function (stdClass $row) use ($products): void {
                $row->product = $products[$row->product_id];
            })->sortBy(fn (stdClass $row): string => $row->product->name)->values();

            return [
                'chamber' => $chambers[$chamberId] ?? throw new LogicException('Chamber not found for the stock summary.'),
                'rows' => $group,
                'boxes' => (int) $group->sum('boxes'),
                'kg' => (float) $group->sum('kg'),
                'value' => (float) $group->sum('value'),
            ];
        })->sortBy(fn (array $block): string => $block['chamber']->branch->name.$block['chamber']->name)->values();
    }

    /**
     * Figures of the available boxes of a product in a chamber.
     *
     * @return array{boxes: int, weight: string, cost: string, mixed_weights: bool}
     */
    private function groupFigures(int $chamberId, int $productId): array
    {
        $figures = Box::query()->toBase()
            ->where('current_chamber_id', $chamberId)
            ->where('product_id', $productId)
            ->where('status', BoxStatus::Available->value)
            ->selectRaw('count(*) as boxes, avg(current_weight_kg) as weight, avg(unit_cost_mxn) as cost, min(current_weight_kg) as min_weight, max(current_weight_kg) as max_weight')
            ->first();

        return [
            'boxes' => (int) $figures->boxes,
            'weight' => number_format((float) $figures->weight, 3, '.', ''),
            'cost' => number_format((float) $figures->cost, 2, '.', ''),
            'mixed_weights' => $figures->min_weight !== $figures->max_weight,
        ];
    }

    public function openEditBox(int $boxId): void
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403);

        $box = Box::query()->where('status', BoxStatus::Available->value)->findOrFail($boxId);

        $this->editBoxId = $box->id;
        $this->editWeight = $box->current_weight_kg;
        $this->editCost = $box->unit_cost_mxn;
        $this->editOriginal = ['weight' => $this->editWeight, 'cost' => $this->editCost];
        $this->editReason = '';

        $this->resetErrorBag();
        Flux::modal('edit-box')->show();
    }

    public function saveEditBox(EditBox $editBox): void
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403);

        $this->validate([
            'editWeight' => ['required', 'numeric', 'gt:0'],
            'editCost' => ['required', 'numeric', 'min:0'],
            'editReason' => ['required', 'string', 'min:5', 'max:500'],
        ], attributes: ['editWeight' => 'peso', 'editCost' => 'costo', 'editReason' => 'motivo']);

        $box = Box::query()->findOrFail($this->editBoxId);

        $done = $this->attempt(fn () => $editBox->handle(
            Auth::user(),
            $box,
            $this->editReason,
            $this->editWeight === $this->editOriginal['weight'] ? null : $this->editWeight,
            $this->editCost === $this->editOriginal['cost'] ? null : $this->editCost,
        ));

        if ($done) {
            Flux::modal('edit-box')->close();
            Flux::toast(text: "Caja {$box->barcode} actualizada.", variant: 'success');
        }
    }

    public function openAdjust(int $chamberId, int $productId): void
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403);

        $figures = $this->groupFigures($chamberId, $productId);

        abort_if($figures['boxes'] === 0, 404);

        $this->adjustChamberId = $chamberId;
        $this->adjustProductId = $productId;
        $this->adjustBoxes = (string) $figures['boxes'];
        $this->adjustWeight = $figures['weight'];
        $this->adjustCost = $figures['cost'];
        $this->adjustOriginal = ['boxes' => $this->adjustBoxes, 'weight' => $this->adjustWeight, 'cost' => $this->adjustCost];
        $this->adjustReason = '';

        $this->resetErrorBag();
        Flux::modal('adjust-stock')->show();
    }

    public function saveAdjust(AdjustStockSummary $adjustStock): void
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403);

        $this->validate([
            'adjustBoxes' => ['required', 'integer', 'min:0', 'max:100000'],
            'adjustWeight' => ['required', 'numeric', 'gt:0'],
            'adjustCost' => ['required', 'numeric', 'min:0'],
            'adjustReason' => ['required', 'string', 'min:5', 'max:500'],
        ], attributes: ['adjustBoxes' => 'cajas', 'adjustWeight' => 'peso por caja', 'adjustCost' => 'costo por caja', 'adjustReason' => 'motivo']);

        $chamber = Chamber::query()->findOrFail($this->adjustChamberId);
        $product = Product::query()->findOrFail($this->adjustProductId);
        $result = [];

        $done = $this->attempt(function () use ($adjustStock, $chamber, $product, &$result): void {
            $result = $adjustStock->handle(
                Auth::user(),
                $chamber,
                $product,
                $this->adjustReason,
                $this->adjustBoxes === $this->adjustOriginal['boxes'] ? null : (int) $this->adjustBoxes,
                $this->adjustWeight === $this->adjustOriginal['weight'] ? null : $this->adjustWeight,
                $this->adjustCost === $this->adjustOriginal['cost'] ? null : $this->adjustCost,
            );
        });

        if (! $done) {
            return;
        }

        $parts = array_filter([
            $result['added'] > 0 ? "{$result['added']} cajas agregadas" : null,
            $result['written_off'] > 0 ? "{$result['written_off']} cajas dadas de baja" : null,
            $result['reweighed'] > 0 ? "peso actualizado en {$result['reweighed']} cajas" : null,
            $result['recosted'] > 0 ? "costo actualizado en {$result['recosted']} cajas" : null,
            $result['skipped_partial'] > 0 ? "{$result['skipped_partial']} cajas parciales conservaron su peso" : null,
        ]);

        Flux::modal('adjust-stock')->close();
        Flux::toast(text: 'Existencias ajustadas: '.implode(', ', $parts).'.', variant: 'success');
    }

    public function render(): View
    {
        $columns = ['id', 'barcode', 'product_id', 'category_id', 'initial_weight_kg', 'current_weight_kg', 'unit_cost_mxn', 'entered_at', 'status', 'current_chamber_id'];
        $isSummary = $this->view === 'summary';

        $summary = ($isSummary ? $this->visibleBoxes()->where('status', BoxStatus::Available->value) : $this->filteredBoxes())->toBase()
            ->selectRaw('count(*) as boxes, coalesce(sum(current_weight_kg), 0) as kg, coalesce(sum(unit_cost_mxn * current_weight_kg / initial_weight_kg), 0) as value')
            ->first();

        $hasAdjustTarget = $this->adjustChamberId !== null && $this->adjustProductId !== null;

        return view('livewire.inventory.stock', [
            'isSummary' => $isSummary,
            'canAdjust' => Auth::user()->isSuperAdmin(),
            'showCosts' => Gate::allows('view-costs'),
            'summaries' => $isSummary ? $this->chamberSummaries() : collect(),
            'editBox' => $this->editBoxId ? Box::query()->with('product:id,name')->find($this->editBoxId) : null,
            'adjustFigures' => $hasAdjustTarget ? $this->groupFigures($this->adjustChamberId, $this->adjustProductId) : null,
            'adjustLabel' => $hasAdjustTarget
                ? Product::query()->whereKey($this->adjustProductId)->value('name').' · '.Chamber::query()->whereKey($this->adjustChamberId)->value('name')
                : '',
            'boxes' => $isSummary ? null : $this->filteredBoxes()
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
            'canSeeAllStatuses' => Auth::user()->seesAllChambers() || Auth::user()->isManager(),
        ]);
    }
}
