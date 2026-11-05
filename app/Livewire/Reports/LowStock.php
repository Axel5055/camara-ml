<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\RequiresSuperAdmin;
use App\Models\AuditLog;
use App\Models\Chamber;
use App\Models\Product;
use App\Models\StockThreshold;
use App\Services\Inventory\LowStockAlerts;
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

    /** Condition (over the joined stock and threshold) that makes a product low on stock. */
    private const IS_LOW = 'case when t.id is null then coalesce(s.boxes, 0) <= '.LowStockAlerts::THRESHOLD.'
        else ((t.min_boxes is not null and coalesce(s.boxes, 0) < t.min_boxes) or (t.min_weight_kg is not null and coalesce(s.kg, 0) < t.min_weight_kg)) end';

    public bool $showAll = false;

    public string $search = '';

    public ?int $chamberFilter = null;

    public ?int $thresholdChamberId = null;

    public ?int $thresholdProductId = null;

    public string $minBoxes = '';

    public string $minWeight = '';

    /**
     * Every product stocked in a chamber (or with a configured threshold) with its current stock. Pairs without a
     * threshold use the default one (LowStockAlerts::THRESHOLD boxes or fewer), the same rule the dashboard uses.
     */
    private function thresholdsQuery(): Builder
    {
        $stock = DB::table('boxes')
            ->selectRaw('current_chamber_id, product_id, count(*) as boxes, sum(current_weight_kg) as kg')
            ->where('status', 'available')
            ->groupBy('current_chamber_id', 'product_id');

        $pairs = DB::table('boxes')
            ->select('origin_chamber_id as chamber_id', 'product_id')
            ->groupBy('origin_chamber_id', 'product_id')
            ->union(DB::table('stock_thresholds')->select('chamber_id', 'product_id'));

        $search = trim($this->search);

        return DB::query()
            ->fromSub($pairs, 'p')
            ->join('chambers', 'chambers.id', '=', 'p.chamber_id')
            ->join('branches', 'branches.id', '=', 'chambers.branch_id')
            ->join('products', 'products.id', '=', 'p.product_id')
            ->leftJoin('stock_thresholds as t', fn ($join) => $join->on('t.chamber_id', '=', 'p.chamber_id')->on('t.product_id', '=', 'p.product_id'))
            ->leftJoinSub($stock, 's', fn ($join) => $join->on('s.current_chamber_id', '=', 'p.chamber_id')->on('s.product_id', '=', 'p.product_id'))
            ->whereRaw('(t.id is not null or (products.is_active = 1 and chambers.is_active = 1))')
            ->when($this->chamberFilter, fn (Builder $query, int $id) => $query->where('p.chamber_id', $id))
            ->when($search !== '', fn (Builder $query) => $query->where('products.name', 'like', "%{$search}%"))
            ->selectRaw('p.chamber_id, p.product_id, t.id as threshold_id, (t.id is null) as is_default,
                branches.name as branch, chambers.name as chamber, products.name as product,
                case when t.id is null then ? else t.min_boxes end as min_boxes, t.min_weight_kg,
                coalesce(s.boxes, 0) as boxes, coalesce(s.kg, 0) as kg,
                ('.self::IS_LOW.') as is_low', [LowStockAlerts::THRESHOLD]);
    }

    private function query(): Builder
    {
        $query = $this->thresholdsQuery();

        if (! $this->showAll) {
            $query->whereRaw(self::IS_LOW);
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
            ->selectRaw('count(*) as thresholds, coalesce(sum(is_low), 0) as low, coalesce(sum(boxes = 0), 0) as out_of_stock')
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
