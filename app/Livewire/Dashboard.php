<?php

namespace App\Livewire;

use App\Enums\BoxStatus;
use App\Enums\IncidentStatus;
use App\Enums\TransferStatus;
use App\Livewire\Concerns\InteractsWithChambers;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Incident;
use App\Models\StockExit;
use App\Models\Transfer;
use App\Services\Inventory\LowStockAlerts;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Panel')]
class Dashboard extends Component
{
    use InteractsWithChambers;

    /** Days shown in the daily chart: 7, 14 or 30. */
    public int $days = 14;

    /** boxes | kg */
    public string $metric = 'boxes';

    /** Chamber to look at; empty means every chamber the user can see, combined. */
    #[Url]
    public ?int $chamberId = null;

    /** Chamber of the products table; always one chamber, never all of them. */
    public ?int $tableChamberId = null;

    /** Category filter of the products table; empty means every category. */
    public ?int $tableCategoryId = null;

    public string $tableSearch = '';

    public function updatedTableChamberId(): void
    {
        $this->tableCategoryId = null;
    }

    public function updatedDays(): void
    {
        if (! in_array($this->days, [7, 14, 30], true)) {
            $this->days = 14;
        }
    }

    public function updatedMetric(): void
    {
        if (! in_array($this->metric, ['boxes', 'kg'], true)) {
            $this->metric = 'boxes';
        }
    }

    /**
     * Restrict a query to the chambers the user can see (everything for administrators).
     *
     * @param  array<int, int>|null  $visible
     */
    private function limit(Builder $query, string $column, ?array $visible): Builder
    {
        return $visible === null ? $query : $query->whereIn($column, $visible);
    }

    /**
     * Totals per calendar day for the last $days days, with zeros for days without movements.
     *
     * @param  array<int, int>|null  $visible
     * @return array{labels: list<string>, entries: list<float|int>, exits: list<float|int>}
     */
    private function dailySeries(?array $visible): array
    {
        $first = CarbonImmutable::now()->subDays($this->days - 1)->startOfDay();
        $column = $this->metric === 'kg' ? 'total_weight_kg' : 'total_boxes';

        $perDay = fn (string $table, string $chamberColumn) => $this->limit(
            DB::table($table)
                ->where('status', 'confirmed')
                ->where('occurred_at', '>=', $first)
                ->selectRaw("date(occurred_at) as day, sum({$column}) as total")
                ->groupBy('day'),
            $chamberColumn,
            $visible,
        )->pluck('total', 'day');

        $entries = $perDay('entries', 'chamber_id');
        $exits = $perDay('exits', 'origin_chamber_id');

        $labels = [];
        $entrySeries = [];
        $exitSeries = [];

        for ($i = 0; $i < $this->days; $i++) {
            $day = $first->addDays($i);
            $labels[] = $day->format('d/m');
            $entrySeries[] = (float) ($entries[$day->format('Y-m-d')] ?? 0);
            $exitSeries[] = (float) ($exits[$day->format('Y-m-d')] ?? 0);
        }

        return ['labels' => $labels, 'entries' => $entrySeries, 'exits' => $exitSeries];
    }

    /**
     * A different badge color for each category, in a stable order (by id) so the same category always looks the same.
     *
     * @return array<int, string>
     */
    private function categoryColors(): array
    {
        $palette = ['sky', 'emerald', 'amber', 'violet', 'rose', 'orange', 'indigo', 'lime', 'pink', 'cyan', 'fuchsia', 'yellow', 'blue', 'red'];

        return Category::query()->orderBy('id')->pluck('id')->values()
            ->mapWithKeys(fn (int $id, int $index): array => [$id => $palette[$index % count($palette)]])
            ->all();
    }

    public function render(LowStockAlerts $lowStock): View
    {
        $user = Auth::user();
        $allChambers = $this->accessibleChambers();
        // The id comes from the browser: it only counts if it is one of the user's own chambers.
        $selected = $this->chamberId !== null ? $allChambers->firstWhere('id', $this->chamberId) : null;
        $visible = $selected ? [$selected->id] : $this->visibleChamberIds();
        $chambers = $selected ? collect([$selected]) : $allChambers;
        $today = CarbonImmutable::now()->startOfDay();

        $showCosts = Gate::allows('view-costs');

        $stockByChamber = $this->limit(
            DB::table('boxes')
                ->selectRaw('current_chamber_id, count(*) as boxes, coalesce(sum(current_weight_kg), 0) as kg')
                ->where('status', BoxStatus::Available->value)
                ->groupBy('current_chamber_id'),
            'current_chamber_id',
            $visible,
        )->get()->keyBy('current_chamber_id');

        $stockByCategory = $this->limit(
            DB::table('boxes')
                ->join('categories', 'categories.id', '=', 'boxes.category_id')
                ->selectRaw('categories.name as category, count(*) as boxes, coalesce(sum(boxes.current_weight_kg), 0) as kg')
                ->where('boxes.status', BoxStatus::Available->value)
                ->groupBy('categories.id', 'categories.name')
                ->orderByDesc('boxes'),
            'boxes.current_chamber_id',
            $visible,
        )->get();

        $todayTotals = fn (string $table, string $chamberColumn) => $this->limit(
            DB::table($table)->where('status', 'confirmed')->where('occurred_at', '>=', $today)
                ->selectRaw('coalesce(sum(total_boxes), 0) as boxes, coalesce(sum(total_weight_kg), 0) as kg'),
            $chamberColumn,
            $visible,
        )->first();

        $inventoryValue = $showCosts
            ? $this->limit(
                DB::table('boxes')->where('status', BoxStatus::Available->value)
                    ->selectRaw('coalesce(sum(unit_cost_mxn * current_weight_kg / initial_weight_kg), 0) as value'),
                'current_chamber_id',
                $visible,
            )->value('value')
            : null;

        $tableAll = $this->tableChamberId === 0 && $allChambers->count() > 1;
        $tableChamber = $tableAll ? null : (($this->tableChamberId ? $allChambers->firstWhere('id', $this->tableChamberId) : null)
            ?? $selected
            ?? $allChambers->first());
        $this->tableChamberId = $tableAll ? 0 : $tableChamber?->id;
        $needle = mb_strtolower(trim($this->tableSearch));

        $chamberSummary = collect();
        if ($tableAll) {
            $availableProducts = fn () => DB::table('boxes')
                ->join('products', 'products.id', '=', 'boxes.product_id')
                ->where('boxes.status', BoxStatus::Available->value)
                ->whereIn('boxes.current_chamber_id', $allChambers->modelKeys());

            $allCategories = $availableProducts()
                ->join('categories as pc', 'pc.id', '=', 'products.category_id')
                ->distinct()->orderBy('pc.name')->pluck('pc.name', 'pc.id');
            $allCategoryId = $allCategories->has($this->tableCategoryId) ? $this->tableCategoryId : null;

            $chamberSummary = $availableProducts()
                ->join('chambers', 'chambers.id', '=', 'boxes.current_chamber_id')
                ->join('branches', 'branches.id', '=', 'chambers.branch_id')
                ->when($allCategoryId, fn (Builder $query) => $query->where('products.category_id', $allCategoryId))
                ->when($needle !== '', fn (Builder $query) => $query->whereRaw("lower(concat(products.name, ' ', products.code)) like ?", ['%'.$needle.'%']))
                ->groupBy('chambers.id', 'chambers.name', 'branches.name')
                ->orderBy('branches.name')->orderBy('chambers.name')
                ->selectRaw('branches.name as branch, chambers.name as chamber, count(*) as boxes, coalesce(sum(boxes.current_weight_kg), 0) as kg'.($showCosts ? ', coalesce(sum(boxes.unit_cost_mxn * boxes.current_weight_kg / boxes.initial_weight_kg), 0) as value' : ''))
                ->get();
        }

        $productStock = $tableChamber
            ? DB::table('boxes')
                ->join('products', 'products.id', '=', 'boxes.product_id')
                ->join('categories as pc', 'pc.id', '=', 'products.category_id')
                ->where('boxes.status', BoxStatus::Available->value)
                ->where('boxes.current_chamber_id', $tableChamber->id)
                ->groupBy('products.id', 'products.name', 'products.code', 'pc.id', 'pc.name')
                ->orderBy('products.name')
                ->selectRaw('products.name as product, products.code, pc.id as category_id, pc.name as category, count(*) as boxes, coalesce(sum(boxes.current_weight_kg), 0) as kg'.($showCosts ? ', coalesce(sum(boxes.unit_cost_mxn * boxes.current_weight_kg / boxes.initial_weight_kg), 0) as value' : ''))
                ->get()
            : collect();

        $tableCategories = $tableAll ? $allCategories : $productStock->unique('category_id')->sortBy('category')->pluck('category', 'category_id');
        $tableCategoryId = $tableCategories->has($this->tableCategoryId) ? $this->tableCategoryId : null;
        $shownStock = $productStock
            ->when($tableCategoryId, fn ($rows) => $rows->where('category_id', $tableCategoryId))
            ->when($needle !== '', fn ($rows) => $rows->filter(fn ($row) => str_contains(mb_strtolower($row->product.' '.$row->code), $needle)))
            ->values();

        $low = $lowStock->for($user)->when($selected, fn ($rows) => $rows->where('chamber_id', $selected->id))->values();
        $series = $this->dailySeries($visible);

        return view('livewire.dashboard', [
            'chambers' => $chambers,
            'tableChamber' => $tableChamber,
            'tableAll' => $tableAll,
            'categoryColors' => $this->categoryColors(),
            'chamberSummary' => $chamberSummary,
            'productStock' => $shownStock,
            'tableCategories' => $tableCategories,
            'tableCategoryId' => $tableCategoryId,
            'tableHasStock' => $productStock->isNotEmpty(),
            'allChambers' => $allChambers,
            'selected' => $selected,
            'stock' => $stockByChamber,
            'categories' => $stockByCategory,
            'totalBoxes' => (int) $stockByChamber->sum('boxes'),
            'totalKg' => (string) $stockByChamber->sum('kg'),
            'entriesToday' => $todayTotals('entries', 'chamber_id'),
            'exitsToday' => $todayTotals('exits', 'origin_chamber_id'),
            'showTransfers' => Gate::allows('operate-inventory'),
            'incoming' => Transfer::query()
                ->where('status', TransferStatus::InTransit)
                ->when($visible !== null, fn (EloquentBuilder $query) => $query->whereIn('dest_chamber_id', $visible))
                ->count(),
            'openIncidents' => $user->isSuperAdmin()
                ? Incident::query()->where('status', IncidentStatus::Open)
                    ->when($visible !== null, fn (EloquentBuilder $query) => $query->whereHas('transfer', fn (EloquentBuilder $transfer) => $transfer
                        ->whereIn('origin_chamber_id', $visible)->orWhereIn('dest_chamber_id', $visible)))
                    ->count()
                : null,
            'inventoryValue' => $inventoryValue,
            'lowStock' => $low,
            'threshold' => LowStockAlerts::THRESHOLD,
            'series' => $series,
            'recentEntries' => Entry::query()
                ->with('chamber:id,name')
                ->where('status', 'confirmed')
                ->when($visible !== null, fn (EloquentBuilder $query) => $query->whereIn('chamber_id', $visible))
                ->orderByDesc('occurred_at')->orderByDesc('id')->limit(5)->get(),
            'recentExits' => StockExit::query()
                ->with('originChamber:id,name', 'destBranch:id,name')
                ->where('status', 'confirmed')
                ->when($visible !== null, fn (EloquentBuilder $query) => $query->whereIn('origin_chamber_id', $visible))
                ->orderByDesc('occurred_at')->orderByDesc('id')->limit(5)->get(),
        ]);
    }
}
