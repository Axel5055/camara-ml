<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\SavesAuditedModels;
use App\Models\Category;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Productos')]
class Products extends Component
{
    use SavesAuditedModels, WithPagination;

    public string $search = '';

    public ?int $categoryFilter = null;

    /** all | active | inactive */
    public string $status = 'all';

    public ?int $editingId = null;

    public ?int $category_id = null;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    public bool $codeLocked = false;

    /**
     * The code of a new product follows its name: the same first nine letters the barcodes are built with.
     * When another product already has it, a number is appended.
     */
    private function suggestCode(string $name): string
    {
        $letters = substr((string) preg_replace('/[^A-Z]/', '', strtoupper(Str::ascii($name))), 0, 9);

        if ($letters === '') {
            return '';
        }

        $base = str_pad($letters, 2, 'X');
        $code = $base;

        for ($suffix = 2; Product::query()->where('code', $code)->exists(); $suffix++) {
            $code = $base.$suffix;
        }

        return $code;
    }

    public function updatedName(): void
    {
        if ($this->editingId === null) {
            $this->code = $this->suggestCode($this->name);
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'categoryFilter', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'categoryFilter', 'status');
        $this->resetPage();
    }

    /**
     * Products matching the search and category (the status filter is applied separately so the cards stay complete).
     *
     * @return Builder<Product>
     */
    private function searchedProducts(): Builder
    {
        $search = trim($this->search);

        return Product::query()
            ->when($this->categoryFilter, fn (Builder $query) => $query->where('category_id', $this->categoryFilter))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")));
    }

    public function create(): void
    {
        Gate::authorize('create', Product::class);

        $this->reset('editingId', 'category_id', 'code', 'name', 'description', 'codeLocked');
        $this->is_active = true;
        $this->resetValidation();
        Flux::modal('product-form')->show();
    }

    public function edit(int $id): void
    {
        $product = Product::query()->findOrFail($id);
        Gate::authorize('update', $product);

        $this->editingId = $product->id;
        $this->category_id = $product->category_id;
        $this->code = $product->code;
        $this->name = $product->name;
        $this->description = (string) $product->description;
        $this->is_active = $product->is_active;
        $this->codeLocked = $product->boxes()->exists();
        $this->resetValidation();
        Flux::modal('product-form')->show();
    }

    public function save(): void
    {
        $product = $this->editingId ? Product::query()->findOrFail($this->editingId) : new Product;
        Gate::authorize($product->exists ? 'update' : 'create', $product->exists ? $product : Product::class);

        $this->name = mb_strtoupper(trim($this->name));

        if (! $product->exists) {
            $this->code = $this->suggestCode($this->name);
        }

        $validated = $this->validate([
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'code' => ['required', 'regex:/^[A-Za-z0-9]{2,30}$/', Rule::unique('products', 'code')->ignore($product->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ], ['code.required' => 'El nombre debe tener al menos una letra para generar el código.']);

        if (! Gate::allows('update', Product::class)) {
            $validated['is_active'] = true;
        }

        $validated['code'] = strtoupper($validated['code']);
        $validated['description'] = $validated['description'] ?: null;

        // The code is the prefix of every barcode already issued, so it can never change once boxes exist.
        if ($product->exists && $product->boxes()->exists() && $validated['code'] !== $product->code) {
            $this->addError('code', 'El código no puede cambiar porque ya existen cajas con este producto.');

            return;
        }

        $this->saveAudited($product, $validated, 'product');

        Flux::modal('product-form')->close();
        Flux::toast(text: 'Producto guardado.', variant: 'success');
    }

    public function toggle(int $id): void
    {
        $product = Product::query()->findOrFail($id);
        Gate::authorize('update', $product);

        $this->saveAudited($product, ['is_active' => ! $product->is_active], 'product');
    }

    public function render(): View
    {
        $totals = $this->searchedProducts()->toBase()
            ->selectRaw('count(*) as total, coalesce(sum(is_active), 0) as active')
            ->first();

        return view('livewire.admin.products', [
            'canCreate' => Gate::allows('create', Product::class),
            'canManage' => Gate::allows('update', Product::class),
            'products' => $this->searchedProducts()
                ->with('category')
                ->withCount(['boxes as stocked_boxes_count' => fn (Builder $query) => $query->where('status', 'available')])
                ->when($this->status === 'active', fn (Builder $query) => $query->where('is_active', true))
                ->when($this->status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
                ->orderBy('name')
                ->paginate(15),
            'totals' => [
                'total' => (int) $totals->total,
                'active' => (int) $totals->active,
                'inactive' => (int) $totals->total - (int) $totals->active,
                'stocked' => $this->searchedProducts()->whereHas('boxes', fn (Builder $query) => $query->where('status', 'available'))->count(),
            ],
            'categories' => Category::query()->orderBy('name')->get(),
            'hasFilters' => trim($this->search) !== '' || $this->categoryFilter || $this->status !== 'all',
        ]);
    }
}
