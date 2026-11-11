<?php

namespace App\Livewire\Reports;

use App\Enums\BoxStatus;
use App\Livewire\Concerns\HasReportFilters;
use App\Support\CsvExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Reporte de inventario')]
class InventoryReport extends Component
{
    use HasReportFilters;

    /** chamber | branch | category | product */
    public string $groupBy = 'chamber';

    /** available | in_transit | dispatched | depleted | written_off | all */
    public string $status = 'available';

    /** Inventory is a snapshot, so the entry-date range starts empty. */
    protected function defaultsToCurrentMonth(): bool
    {
        return false;
    }

    /**
     * Boxes with their reference chamber (current one, or the origin when they already left).
     */
    private function baseQuery(): Builder
    {
        $location = 'coalesce(boxes.current_chamber_id, boxes.origin_chamber_id)';

        $query = DB::table('boxes')
            ->join('chambers', fn ($join) => $join->on(DB::raw($location), '=', 'chambers.id'))
            ->join('branches', 'branches.id', '=', 'chambers.branch_id')
            ->join('products', 'products.id', '=', 'boxes.product_id')
            ->join('categories', 'categories.id', '=', 'boxes.category_id')
            ->when(BoxStatus::tryFrom($this->status), fn (Builder $query, BoxStatus $status) => $query->where('boxes.status', $status->value))
            ->when($this->chamberId, fn (Builder $query, int $id) => $query->whereRaw("$location = ?", [$id]))
            ->when($this->categoryId, fn (Builder $query, int $id) => $query->where('boxes.category_id', $id))
            ->when($this->productId, fn (Builder $query, int $id) => $query->where('boxes.product_id', $id));

        $this->limitToVisibleChambers($query, ['chambers.id']);
        $this->applyDateRange($query, 'boxes.entered_at');

        return $query;
    }

    private const AGGREGATES = 'count(*) as boxes, coalesce(sum(boxes.current_weight_kg), 0) as kg,
        coalesce(sum(case when boxes.status = \'available\' and boxes.current_weight_kg < boxes.initial_weight_kg then 1 else 0 end), 0) as partial_boxes,
        coalesce(sum(boxes.unit_cost_mxn * boxes.current_weight_kg / boxes.initial_weight_kg), 0) as cost';

    private function groupedQuery(): Builder
    {
        [$label, $extra, $group] = match ($this->groupBy) {
            'branch' => ['branches.name', 'NULL', ['branches.id', 'branches.name']],
            'category' => ['categories.name', 'NULL', ['categories.id', 'categories.name']],
            'product' => ['products.name', 'categories.name', ['products.id', 'products.name', 'categories.name']],
            default => ['chambers.name', 'branches.name', ['chambers.id', 'chambers.name', 'branches.name']],
        };

        return $this->baseQuery()
            ->selectRaw("$label as label, $extra as extra, ".self::AGGREGATES)
            ->groupBy($group)
            ->orderBy('label');
    }

    public function clearFilters(): void
    {
        $this->reset('groupBy', 'status', 'dateFrom', 'dateTo', 'chamberId', 'categoryId', 'productId');
        $this->resetPage();
    }

    public function export(): StreamedResponse
    {
        return CsvExport::download(
            'inventario-'.now()->format('Ymd-His').'.csv',
            ['Grupo', 'Detalle', 'Cajas', 'Cajas parciales', 'Peso (kg)', 'Costo histórico restante (MXN)'],
            $this->groupedQuery()->cursor(),
            fn ($row): array => [$row->label, $row->extra, $row->boxes, $row->partial_boxes, $row->kg, number_format((float) $row->cost, 2, '.', '')],
        );
    }

    public function render(): View
    {
        return view('livewire.reports.inventory-report', [
            'rows' => $this->groupedQuery()->get(),
            'totals' => $this->baseQuery()->selectRaw(self::AGGREGATES)->first(),
            'hasFilters' => $this->groupBy !== 'chamber' || $this->status !== 'available' || $this->dateFrom !== '' || $this->dateTo !== ''
                || $this->chamberId || $this->categoryId || $this->productId,
            ...$this->filterOptions(),
        ]);
    }
}
