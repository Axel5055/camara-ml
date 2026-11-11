<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\SavesAuditedModels;
use App\Models\Branch;
use App\Models\Chamber;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Sucursales')]
class Branches extends Component
{
    use SavesAuditedModels, WithPagination;

    public string $search = '';

    /** all | active | inactive */
    public string $status = 'all';

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $address = '';

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
     * Branches matching the search (the status filter is applied separately so the cards stay complete).
     *
     * @return Builder<Branch>
     */
    private function searchedBranches(): Builder
    {
        $search = trim($this->search);

        return Branch::query()->visibleTo(Auth::user())->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->where('name', 'like', "%{$search}%")
            ->orWhere('code', 'like', "%{$search}%")
            ->orWhere('address', 'like', "%{$search}%")));
    }

    public function create(): void
    {
        Gate::authorize('create', Branch::class);

        $this->reset('editingId', 'code', 'name', 'address');
        $this->is_active = true;
        $this->resetValidation();
        Flux::modal('branch-form')->show();
    }

    public function edit(int $id): void
    {
        $branch = Branch::query()->findOrFail($id);
        Gate::authorize('update', $branch);

        $this->editingId = $branch->id;
        $this->code = $branch->code;
        $this->name = $branch->name;
        $this->address = (string) $branch->address;
        $this->is_active = $branch->is_active;
        $this->resetValidation();
        Flux::modal('branch-form')->show();
    }

    public function save(): void
    {
        $branch = $this->editingId ? Branch::query()->findOrFail($this->editingId) : new Branch;
        Gate::authorize($branch->exists ? 'update' : 'create', $branch->exists ? $branch : Branch::class);

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('branches', 'code')->ignore($branch->id)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['address'] = $validated['address'] ?: null;

        $this->saveAudited($branch, $validated, 'branch');

        Flux::modal('branch-form')->close();
        Flux::toast(text: 'Sucursal guardada.', variant: 'success');
    }

    public function toggle(int $id): void
    {
        $branch = Branch::query()->findOrFail($id);
        Gate::authorize('update', $branch);

        $this->saveAudited($branch, ['is_active' => ! $branch->is_active], 'branch');
    }

    public function render(): View
    {
        $totals = $this->searchedBranches()->toBase()
            ->selectRaw('count(*) as total, coalesce(sum(is_active), 0) as active')
            ->first();

        return view('livewire.admin.branches', [
            'canManage' => Gate::allows('create', Branch::class),
            'branches' => $this->searchedBranches()
                ->withCount(['chambers' => function (Builder $query): void {
                    /** @var Builder<Chamber> $query */
                    $query->accessibleBy(Auth::user());
                }])
                ->when($this->status === 'active', fn (Builder $query) => $query->where('is_active', true))
                ->when($this->status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
                ->orderBy('name')
                ->paginate(15),
            'totals' => [
                'total' => (int) $totals->total,
                'active' => (int) $totals->active,
                'inactive' => (int) $totals->total - (int) $totals->active,
                'chambers' => Chamber::query()->accessibleBy(Auth::user())->whereIn('branch_id', $this->searchedBranches()->select('id'))->count(),
            ],
            'hasFilters' => trim($this->search) !== '' || $this->status !== 'all',
        ]);
    }
}
