<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\RequiresSuperAdmin;
use App\Models\AuditLog;
use App\Models\Chamber;
use App\Models\Product;
use App\Models\StockThreshold;
use App\Support\CsvExport;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Existencias bajas')]
class LowStock extends Component
{
    use RequiresSuperAdmin;

    public bool $showAll = false;

    public string $search = '';

    public ?int $chamberFilter = null;

    public ?int $thresholdChamberId = null;

    public ?int $thresholdProductId = null;

    public string $minBoxes = '';

    public string $minWeight = '';

    /**
     * Every configured threshold with its current stock, narrowed by the search and chamber filters.
     */
    private function thresholdsQuery(): Builder
    {
        $stock = DB::table('boxes')
            ->selectRaw('current_chamber_id, product_id, count(*) as boxes, sum(current_weight_kg) as kg')
            ->where('status', 'available')
            ->groupBy('current_chamber_id', 'product_id');

        $search = trim($this->search);

        return DB::table('stock_thresholds as t')
            ->join('chambers', 'chambers.id', '=', 't.chamber_id')
            ->join('branches', 'branches.id', '=', 'chambers.branch_id')
            ->join('products', 'products.id', '=', 't.product_id')
            ->leftJoinSub($stock, 's', fn ($join) => $join->on('s.current_chamber_id', '=', 't.chamber_id')->on('s.product_id', '=', 't.product_id'))
            ->when($this->chamberFilter, fn (Builder $query, int $id) => $query->where('t.chamber_id', $id))
            ->when($search !== '', fn (Builder $query) => $query->where('products.name', 'like', "%{$search}%"))
            ->selectRaw('t.id as threshold_id, branches.name as branch, chambers.name as chamber, products.name as product,
                t.min_boxes, t.min_weight_kg, coalesce(s.boxes, 0) as boxes, coalesce(s.kg, 0) as kg');
    }

    private function query(): Builder
    {
        $query = $this->thresholdsQuery();

        if (! $this->showAll) {
            $query->whereRaw('((t.min_boxes is not null and coalesce(s.boxes, 0) < t.min_boxes) or (t.min_weight_kg is not null and coalesce(s.kg, 0) < t.min_weight_kg))');
        }

        return $query->orderBy('branches.name')->orderBy('chambers.name')->orderBy('products.name');
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'chamberFilter', 'showAll');
    }

    public function saveThreshold(): void
    {
        $validated = $this->validate([
            'thresholdChamberId' => ['required', Rule::exists('chambers', 'id')],
            'thresholdProductId' => ['required', Rule::exists('products', 'id')],
            'minBoxes' => ['nullable', 'integer', 'min:0'],
            'minWeight' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($this->minBoxes === '' && $this->minWeight === '') {
            $this->addError('minBoxes', 'Indica un mínimo de cajas o de kilogramos.');

            return;
        }

        $threshold = StockThreshold::query()->firstOrNew([
            'chamber_id' => $validated['thresholdChamberId'],
            'product_id' => $validated['thresholdProductId'],
        ]);
        $old = $threshold->exists ? $threshold->only(['min_boxes', 'min_weight_kg']) : null;

        $threshold->fill([
            'min_boxes' => $this->minBoxes === '' ? null : (int) $this->minBoxes,
            'min_weight_kg' => $this->minWeight === '' ? null : $this->minWeight,
        ])->save();

        AuditLog::record(Auth::user(), 'stock_threshold.saved', $threshold, $old, $threshold->only(['chamber_id', 'product_id', 'min_boxes', 'min_weight_kg']));

        $this->reset('thresholdChamberId', 'thresholdProductId', 'minBoxes', 'minWeight');
        Flux::modal('threshold-form')->close();
        Flux::toast(text: 'Umbral guardado.', variant: 'success');
    }

    public function deleteThreshold(int $id): void
    {
        $threshold = StockThreshold::query()->findOrFail($id);
        AuditLog::record(Auth::user(), 'stock_threshold.deleted', $threshold, $threshold->only(['chamber_id', 'product_id', 'min_boxes', 'min_weight_kg']), null);
        $threshold->delete();
    }

    public function export(): StreamedResponse
    {
        return CsvExport::download(
            'existencias-bajas-'.now()->format('Ymd-His').'.csv',
            ['Sucursal', 'Cámara', 'Producto', 'Cajas', 'Mínimo de cajas', 'Peso (kg)', 'Mínimo de kg'],
            $this->query()->cursor(),
            fn ($r): array => [$r->branch, $r->chamber, $r->product, $r->boxes, $r->min_boxes, $r->kg, $r->min_weight_kg],
        );
    }

    public function render(): View
    {
        $summary = DB::query()
            ->fromSub($this->thresholdsQuery(), 'x')
            ->selectRaw('count(*) as thresholds,
                coalesce(sum((min_boxes is not null and boxes < min_boxes) or (min_weight_kg is not null and kg < min_weight_kg)), 0) as low,
                coalesce(sum(boxes = 0), 0) as out_of_stock')
            ->first();

        return view('livewire.reports.low-stock', [
            'rows' => $this->query()->get(),
            'totals' => [
                'thresholds' => (int) $summary->thresholds,
                'low' => (int) $summary->low,
                'empty' => (int) $summary->out_of_stock,
                'healthy' => (int) $summary->thresholds - (int) $summary->low,
            ],
            'hasFilters' => trim($this->search) !== '' || $this->chamberFilter || $this->showAll,
            'chambers' => Chamber::query()->with('branch:id,name')->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
