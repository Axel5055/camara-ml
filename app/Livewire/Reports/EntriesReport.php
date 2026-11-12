<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\HasReportFilters;
use App\Support\CsvExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Reporte de entradas')]
class EntriesReport extends Component
{
    use HasReportFilters;

    private function baseQuery(): Builder
    {
        $query = DB::table('entry_items')
            ->join('entries', 'entries.id', '=', 'entry_items.entry_id')
            ->join('products', 'products.id', '=', 'entry_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->join('chambers', 'chambers.id', '=', 'entries.chamber_id')
            ->join('branches', 'branches.id', '=', 'chambers.branch_id')
            ->join('users', 'users.id', '=', 'entries.user_id')
            ->where('entries.status', 'confirmed')
            ->when($this->chamberId, fn (Builder $query, int $id) => $query->where('entries.chamber_id', $id))
            ->when($this->categoryId, fn (Builder $query, int $id) => $query->where('products.category_id', $id))
            ->when($this->productId, fn (Builder $query, int $id) => $query->where('entry_items.product_id', $id))
            ->when($this->userId, fn (Builder $query, int $id) => $query->where('entries.user_id', $id));

        $this->limitToVisibleChambers($query, ['entries.chamber_id']);
        $this->applyDateRange($query, 'entries.occurred_at');

        return $query;
    }

    private function rowsQuery(): Builder
    {
        return $this->baseQuery()
            ->select([
                'entries.occurred_at', 'entries.reference', 'branches.name as branch', 'chambers.name as chamber',
                'products.name as product', 'categories.name as category', 'entry_items.boxes_count',
                'entry_items.total_weight_kg', 'entry_items.price_per_kg_mxn', 'entry_items.total_cost_mxn', 'users.name as user',
            ])
            ->orderByDesc('entries.occurred_at')->orderByDesc('entry_items.id');
    }

    public function clearFilters(): void
    {
        $this->reset('dateFrom', 'dateTo', 'chamberId', 'categoryId', 'productId', 'userId');
        $this->mountHasReportFilters();
        $this->resetPage();
    }

    public function export(): StreamedResponse
    {
        return CsvExport::download(
            'entradas-'.now()->format('Ymd-His').'.csv',
            ['Fecha', 'Referencia', 'Sucursal', 'Cámara', 'Producto', 'Categoría', 'Cajas', 'Peso (kg)', 'Costo por kg (MXN)', 'Importe (MXN)', 'Registró'],
            $this->rowsQuery()->cursor(),
            fn ($r): array => [$r->occurred_at, $r->reference, $r->branch, $r->chamber, $r->product, $r->category, $r->boxes_count, $r->total_weight_kg, $r->price_per_kg_mxn, $r->total_cost_mxn, $r->user],
        );
    }

    public function render(): View
    {
        return view('livewire.reports.entries-report', [
            'rows' => $this->rowsQuery()->paginate(25),
            'totals' => $this->baseQuery()
                ->selectRaw('count(distinct entries.id) as entries, coalesce(sum(entry_items.boxes_count), 0) as boxes, coalesce(sum(entry_items.total_weight_kg), 0) as kg, coalesce(sum(entry_items.total_cost_mxn), 0) as cost')
                ->first(),
            'hasFilters' => $this->chamberId || $this->categoryId || $this->productId || $this->userId
                || $this->dateFrom !== now()->startOfMonth()->format('Y-m-d') || $this->dateTo !== now()->format('Y-m-d'),
            ...$this->filterOptions(),
        ]);
    }
}
