<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\SavesAuditedModels;
use App\Models\Category;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Categorías')]
class Categories extends Component
{
    use SavesAuditedModels, WithPagination;

    public string $search = '';

    /** all | active | inactive */
    public string $status = 'all';

    public ?int $editingId = null;

    public string $name = '';

    public bool $is_active = true;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
    }

    /**
     * Categories matching the search (the status filter is applied separately so the cards stay complete).
     *
     * @return Builder<Category>
     */
    private function searchedCategories(): Builder
    {
        $search = trim($this->search);

        return Category::query()->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
    }

    public function create(): void
    {
        Gate::authorize('create', Category::class);

        $this->reset('editingId', 'name');
        $this->is_active = true;
        $this->resetValidation();
        Flux::modal('category-form')->show();
    }

    public function edit(int $id): void
    {
        $category = Category::query()->findOrFail($id);
        Gate::authorize('update', $category);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->is_active = $category->is_active;
        $this->resetValidation();
        Flux::modal('category-form')->show();
    }

    public function save(): void
    {
        $category = $this->editingId ? Category::query()->findOrFail($this->editingId) : new Category;
        Gate::authorize($category->exists ? 'update' : 'create', $category->exists ? $category : Category::class);

        $this->name = mb_strtoupper(trim($this->name));

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category->id)],
            'is_active' => ['boolean'],
        ]);

        $this->saveAudited($category, $validated, 'category');

        Flux::modal('category-form')->close();
        Flux::toast(text: 'Categoría guardada.', variant: 'success');
    }

    public function toggle(int $id): void
    {
        $category = Category::query()->findOrFail($id);
        Gate::authorize('update', $category);

        $this->saveAudited($category, ['is_active' => ! $category->is_active], 'category');
    }

    public function render(): View
    {
        $totals = $this->searchedCategories()->toBase()
            ->selectRaw('count(*) as total, coalesce(sum(is_active), 0) as active')
            ->first();

        return view('livewire.admin.categories', [
            'canManage' => Gate::allows('create', Category::class),
            'categories' => $this->searchedCategories()
                ->withCount('products')
                ->when($this->status === 'active', fn (Builder $query) => $query->where('is_active', true))
                ->when($this->status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
                ->orderBy('name')
                ->paginate(15),
            'totals' => [
                'total' => (int) $totals->total,
                'active' => (int) $totals->active,
                'inactive' => (int) $totals->total - (int) $totals->active,
                'products' => Product::query()->whereIn('category_id', $this->searchedCategories()->select('id'))->count(),
            ],
            'hasFilters' => trim($this->search) !== '' || $this->status !== 'all',
        ]);
    }
}
