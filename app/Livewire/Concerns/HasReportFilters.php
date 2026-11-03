<?php

namespace App\Livewire\Concerns;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Chamber;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Livewire\WithPagination;

/**
 * Filters shared by the administrator reports (date range, chamber, category, product, user).
 */
trait HasReportFilters
{
    use RequiresSuperAdmin, WithPagination;

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $chamberId = null;

    public ?int $categoryId = null;

    public ?int $productId = null;

    public ?int $userId = null;

    /** Reports over movements start on the current month; snapshot reports override this. */
    protected function defaultsToCurrentMonth(): bool
    {
        return true;
    }

    public function mountHasReportFilters(): void
    {
        if ($this->defaultsToCurrentMonth()) {
            $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
            $this->dateTo = now()->format('Y-m-d');
        }
    }

    public function updated(string $property): void
    {
        if ($property === 'categoryId') {
            $this->productId = null;
        }

        $this->resetPage();
    }

    protected function applyDateRange(Builder $query, string $column): void
    {
        $query
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->where($column, '>=', $this->dateFrom.' 00:00:00'))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->where($column, '<=', $this->dateTo.' 23:59:59'));
    }

    /**
     * Options for the filter selects.
     *
     * @return array<string, mixed>
     */
    protected function filterOptions(): array
    {
        return [
            'chambers' => Chamber::query()->with('branch:id,name')->orderBy('name')->get(),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()
                ->when($this->categoryId, fn ($query, int $id) => $query->where('category_id', $id))
                ->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
