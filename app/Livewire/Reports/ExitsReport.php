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

#[Title('Reporte de salidas')]
class ExitsReport extends Component
{
    use HasReportFilters;

    public ?int $destBranchId = null;

    public string $requester = '';

    private function baseQuery(): Builder
    {
        $query = DB::table('exit_items')
            ->join('exits', 'exits.id', '=', 'exit_items.exit_id')
            ->join('boxes', 'boxes.id', '=', 'exit_items.box_id')
            ->join('products', 'products.id', '=', 'boxes.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->join('chambers as origin', 'origin.id', '=', 'exits.origin_chamber_id')
            ->join('branches as dest', 'dest.id', '=', 'exits.dest_branch_id')
            ->leftJoin('chambers as dest_chamber', 'dest_chamber.id', '=', 'exits.dest_chamber_id')
            ->join('users', 'users.id', '=', 'exits.user_id')
            ->where('exits.status', 'confirmed')
            ->where('exit_items.is_active', true)
            ->when($this->chamberId, fn (Builder $query, int $id) => $query->where('exits.origin_chamber_id', $id))
            ->when($this->destBranchId, fn (Builder $query, int $id) => $query->where('exits.dest_branch_id', $id))
            ->when($this->categoryId, fn (Builder $query, int $id) => $query->where('products.category_id', $id))
            ->when($this->productId, fn (Builder $query, int $id) => $query->where('boxes.product_id', $id))
            ->when($this->userId, fn (Builder $query, int $id) => $query->where('exits.user_id', $id))
            ->when($this->requester !== '', fn (Builder $query) => $query->where('exits.requester_name', 'like', "%{$this->requester}%"));

        $this->limitToVisibleChambers($query, ['exits.origin_chamber_id', 'exits.dest_chamber_id']);
        $this->applyDateRange($query, 'exits.occurred_at');

        return $query;
    }

    private function rowsQuery(): Builder
    {
        return $this->baseQuery()
            ->selectRaw('exits.id as exit_id, exits.occurred_at, exits.reference, exits.requester_name, origin.name as origin,
                dest.name as dest, dest_chamber.name as dest_chamber, products.name as product, categories.name as category,
                users.name as user, count(*) as boxes, sum(exit_items.weight_out_kg) as kg, sum(exit_items.cost_out_mxn) as cost')
            ->groupBy('exits.id', 'exits.occurred_at', 'exits.reference', 'exits.requester_name', 'origin.name', 'dest.name',
                'dest_chamber.name', 'products.id', 'products.name', 'categories.name', 'users.name')
            ->orderByDesc('exits.occurred_at')->orderByDesc('exits.id');
    }

    public function clearFilters(): void
    {
        $this->reset('dateFrom', 'dateTo', 'chamberId', 'categoryId', 'productId', 'userId', 'destBranchId', 'requester');
        $this->mountHasReportFilters();
        $this->resetPage();
    }

    public function export(): StreamedResponse
    {
        return CsvExport::download(
            'salidas-'.now()->format('Ymd-His').'.csv',
            ['Fecha', 'Referencia', 'Solicitante', 'Cámara de origen', 'Sucursal destino', 'Cámara destino', 'Producto', 'Categoría', 'Cajas', 'Peso (kg)', 'Costo histórico (MXN)', 'Registró'],
            $this->rowsQuery()->cursor(),
            fn ($r): array => [$r->occurred_at, $r->reference, $r->requester_name, $r->origin, $r->dest, $r->dest_chamber, $r->product, $r->category, $r->boxes, $r->kg, $r->cost, $r->user],
        );
    }

    public function render(): View
    {
        return view('livewire.reports.exits-report', [
            'rows' => $this->rowsQuery()->paginate(25),
            'totals' => $this->baseQuery()
                ->selectRaw('count(distinct exits.id) as exits, count(*) as boxes, coalesce(sum(exit_items.weight_out_kg), 0) as kg, coalesce(sum(exit_items.cost_out_mxn), 0) as cost')
                ->first(),
            'hasFilters' => $this->chamberId || $this->categoryId || $this->productId || $this->userId || $this->destBranchId
                || $this->requester !== ''
                || $this->dateFrom !== now()->startOfMonth()->format('Y-m-d') || $this->dateTo !== now()->format('Y-m-d'),
            ...$this->filterOptions(),
        ]);
    }
}
