<?php

namespace App\Livewire\Concerns;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Chamber;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

/**
 * Filters shared by the administrator reports (date range, chamber, category, product, user).
 */
trait HasReportFilters
{
    use AuthorizesReports, InteractsWithChambers, WithPagination;

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
     * Restricts a query to the chambers the user may see (a no-op for super administrators). The column is the
     * chamber id of the row; give several when a row belongs to a visible chamber through either of them.
     *
     * @param  array<int, string>  $columns
     */
    protected function limitToVisibleChambers(Builder $query, array $columns): void
    {
        $visible = $this->visibleChamberIds();

        if ($visible === null) {
            return;
        }

        $query->where(function (Builder $scope) use ($columns, $visible): void {
            foreach ($columns as $column) {
                $scope->orWhereIn($column, $visible);
            }
        });
    }

    /**
     * Users for the filter: everyone for super administrators; for managers only the warehouse users of their
     * visible chambers.
     *
     * @return Collection<int, User>
     */
    private function filterableUsers(): Collection
    {
        $user = Auth::user();

        return User::query()
            ->when($user->isManager(), fn ($query) => $query->whereHas('chambers', fn ($chambers) => $chambers
                ->whereIn('chambers.id', Chamber::query()->accessibleBy($user)->select('chambers.id'))))
            ->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Options for the filter selects.
     *
     * @return array<string, mixed>
     */
    protected function filterOptions(): array
    {
        return [
            'chambers' => Chamber::query()->accessibleBy(Auth::user())->with('branch:id,name')->orderBy('name')->get(),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()
                ->when($this->categoryId, fn ($query, int $id) => $query->where('category_id', $id))
                ->orderBy('name')->get(['id', 'name', 'code']),
            'users' => $this->filterableUsers(),
        ];
    }
}
