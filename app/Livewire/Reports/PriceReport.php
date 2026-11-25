<?php

namespace App\Livewire\Reports;

use App\Models\Category;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Precios por kilo')]
class PriceReport extends Component
{
    use WithPagination;

    private const PER_PAGE = 20;

    public ?int $productId = null;

    public ?int $categoryId = null;

    public ?int $historyProductId = null;

    /**
     * Livewire boot hook: runs on the first load and on every later request of the component.
     */
    public function bootPriceReport(): void
    {
        abort_unless(Gate::allows('view-price-report'), 403);
    }

    public function updatedProductId(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('productId', 'categoryId');
        $this->resetPage();
    }

    public function showHistory(int $productId): void
    {
        $this->historyProductId = Product::query()->findOrFail($productId)->id;
        Flux::modal('price-history')->show();
    }

    /**
     * Every box that carries a price: from entries, additions and adjustments, leaving out the ones of reversed entries.
     */
    private function pricedBoxes(): Builder
    {
        return DB::table('boxes')
            ->leftJoin('entry_items', 'entry_items.id', '=', 'boxes.entry_item_id')
            ->leftJoin('entries', 'entries.id', '=', 'entry_items.entry_id')
            ->where(fn (Builder $query) => $query->whereNull('entries.id')->orWhere('entries.status', 'confirmed'));
    }

    /**
     * Price changes of the given products, oldest first: one row each time the price per kilo differs from the one
     * before, with the day it first appeared and the change against the previous price.
     *
     * @param  array<int, int>  $productIds
     * @return Collection<int|string, list<array{price: numeric-string, since: string, previous: numeric-string|null, change: numeric-string|null}>>
     */
    private function historyOf(array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        $days = $this->pricedBoxes()
            ->whereIn('boxes.product_id', $productIds)
            ->groupBy('boxes.product_id', DB::raw('date(boxes.entered_at)'), 'boxes.price_per_kg_mxn')
            ->orderBy('boxes.product_id')->orderBy(DB::raw('date(boxes.entered_at)'))->orderBy(DB::raw('max(boxes.id)'))
            ->selectRaw('boxes.product_id, date(boxes.entered_at) as day, boxes.price_per_kg_mxn as price')
            ->get();

        return $days->groupBy('product_id')->map(function (Collection $rows): array {
            $changes = [];
            $previous = null;

            foreach ($rows as $row) {
                $price = number_format((float) $row->price, 2, '.', '');

                if ($price === $previous) {
                    continue;
                }

                $changes[] = [
                    'price' => $price,
                    'since' => (string) $row->day,
                    'previous' => $previous,
                    'change' => $previous === null ? null : bcsub($price, $previous, 2),
                ];
                $previous = $price;
            }

            return $changes;
        });
    }

    public function render(): View
    {
        $products = Product::query()
            ->with('category:id,name')
            ->when($this->productId, fn ($query, int $id) => $query->whereKey($id))
            ->when($this->categoryId, fn ($query, int $id) => $query->where('category_id', $id))
            ->orderBy('name')
            ->paginate(self::PER_PAGE, ['id', 'name', 'code', 'category_id', 'is_active']);

        $histories = $this->historyOf($products->pluck('id')->all());
        $products->getCollection()->each(function (Product $product) use ($histories): void {
            $product->setAttribute('price_changes', collect($histories->get($product->id, [])));
        });

        $history = $this->historyProductId === null ? null : collect($this->historyOf([$this->historyProductId])->get($this->historyProductId, []))->reverse()->values();

        return view('livewire.reports.price-report', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'allProducts' => Product::query()->orderBy('name')->get(['id', 'name', 'code']),
            'historyProduct' => $this->historyProductId === null ? null : Product::query()->with('category:id,name')->find($this->historyProductId),
            'history' => $history,
            'hasFilters' => $this->productId !== null || $this->categoryId !== null,
        ]);
    }
}
