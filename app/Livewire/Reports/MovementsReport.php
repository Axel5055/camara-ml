<?php

namespace App\Livewire\Reports;

use App\Enums\MovementType;
use App\Livewire\Concerns\HasReportFilters;
use App\Support\CsvExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Reporte de movimientos')]
class MovementsReport extends Component
{
    use HasReportFilters;

    public string $type = '';

    private function baseQuery(): Builder
    {
        $query = DB::table('box_movements')
            ->join('boxes', 'boxes.id', '=', 'box_movements.box_id')
            ->join('users', 'users.id', '=', 'box_movements.user_id')
            ->leftJoin('chambers as from_chamber', 'from_chamber.id', '=', 'box_movements.from_chamber_id')
            ->leftJoin('chambers as to_chamber', 'to_chamber.id', '=', 'box_movements.to_chamber_id')
            ->leftJoin('branches as to_branch', 'to_branch.id', '=', 'box_movements.to_branch_id')
            ->when(MovementType::tryFrom($this->type), fn (Builder $query, MovementType $type) => $query->where('box_movements.type', $type->value))
            ->when($this->userId, fn (Builder $query, int $id) => $query->where('box_movements.user_id', $id))
            ->when($this->chamberId, fn (Builder $query, int $id) => $query->where(fn (Builder $query) => $query
                ->where('box_movements.from_chamber_id', $id)->orWhere('box_movements.to_chamber_id', $id)))
            ->when($this->categoryId, fn (Builder $query, int $id) => $query->where('boxes.category_id', $id))
            ->when($this->productId, fn (Builder $query, int $id) => $query->where('boxes.product_id', $id));

        $this->limitToVisibleChambers($query, ['box_movements.from_chamber_id', 'box_movements.to_chamber_id']);
        $this->applyDateRange($query, 'box_movements.occurred_at');

        return $query;
    }

    private function rowsQuery(): Builder
    {
        return $this->baseQuery()
            ->select([
                'box_movements.id', 'box_movements.occurred_at', 'box_movements.type', 'boxes.barcode',
                'from_chamber.name as from_chamber', 'to_chamber.name as to_chamber', 'to_branch.name as to_branch',
                'box_movements.weight_before_kg', 'box_movements.weight_after_kg', 'users.name as user', 'box_movements.notes',
            ])
            ->orderByDesc('box_movements.occurred_at')->orderByDesc('box_movements.id');
    }

    public function clearFilters(): void
    {
        $this->reset('type', 'dateFrom', 'dateTo', 'chamberId', 'categoryId', 'productId', 'userId');
        $this->mountHasReportFilters();
        $this->resetPage();
    }

    public function export(): StreamedResponse
    {
        return CsvExport::download(
            'movimientos-'.now()->format('Ymd-His').'.csv',
            ['Fecha', 'Movimiento', 'Código de barras', 'Origen', 'Destino', 'Peso antes (kg)', 'Peso después (kg)', 'Usuario', 'Notas'],
            $this->rowsQuery()->cursor(),
            fn ($r): array => [$r->occurred_at, MovementType::from($r->type)->label(), $r->barcode, $r->from_chamber, $r->to_chamber ?? $r->to_branch, $r->weight_before_kg, $r->weight_after_kg, $r->user, $r->notes],
        );
    }

    public function render(): View
    {
        return view('livewire.reports.movements-report', [
            'rows' => $this->rowsQuery()->paginate(25),
            'totals' => $this->baseQuery()
                ->selectRaw('count(*) as movements, count(distinct box_movements.box_id) as boxes, count(distinct box_movements.user_id) as users, count(distinct box_movements.type) as types')
                ->first(),
            'types' => MovementType::cases(),
            'hasFilters' => $this->type !== '' || $this->chamberId || $this->categoryId || $this->productId || $this->userId
                || $this->dateFrom !== now()->startOfMonth()->format('Y-m-d') || $this->dateTo !== now()->format('Y-m-d'),
            ...$this->filterOptions(),
        ]);
    }
}
