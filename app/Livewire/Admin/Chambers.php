<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\SavesAuditedModels;
use App\Models\Box;
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

#[Title('Cámaras')]
class Chambers extends Component
{
    use SavesAuditedModels, WithPagination;

    public string $search = '';

    public ?int $branchFilter = null;

    /** all | active | inactive */
    public string $status = 'all';

    public ?int $editingId = null;

    public ?int $branch_id = null;

    public string $code = '';

    public string $name = '';

    public bool $is_active = true;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'branchFilter', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'branchFilter', 'status');
        $this->resetPage();
    }

    /**
     * Chambers matching the search and branch (the status filter is applied separately so the cards stay complete).
     *
     * @return Builder<Chamber>
     */
    private function searchedChambers(): Builder
    {
        $search = trim($this->search);

        return Chamber::query()
            ->accessibleBy(Auth::user())
            ->when($this->branchFilter, fn (Builder $query) => $query->where('branch_id', $this->branchFilter))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")));
    }

    public function create(): void
    {
        Gate::authorize('create', Chamber::class);

        $this->reset('editingId', 'branch_id', 'code', 'name');
        $this->is_active = true;
        $this->resetValidation();
        Flux::modal('chamber-form')->show();
    }

    public function edit(int $id): void
    {
        $chamber = Chamber::query()->findOrFail($id);
        Gate::authorize('update', $chamber);

        $this->editingId = $chamber->id;
        $this->branch_id = $chamber->branch_id;
        $this->code = $chamber->code;
        $this->name = $chamber->name;
        $this->is_active = $chamber->is_active;
        $this->resetValidation();
        Flux::modal('chamber-form')->show();
    }

    public function save(): void
    {
        $chamber = $this->editingId ? Chamber::query()->findOrFail($this->editingId) : new Chamber;
        Gate::authorize($chamber->exists ? 'update' : 'create', $chamber->exists ? $chamber : Chamber::class);

        $validated = $this->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')],
            'code' => ['required', 'string', 'max:20', Rule::unique('chambers', 'code')->ignore($chamber->id)],
            'name' => ['required', 'string', 'max:255', Rule::unique('chambers', 'name')->where('branch_id', $this->branch_id)->ignore($chamber->id)],
            'is_active' => ['boolean'],
        ]);

        $validated['code'] = strtoupper($validated['code']);

        $this->saveAudited($chamber, $validated, 'chamber');

        Flux::modal('chamber-form')->close();
        Flux::toast(text: 'Cámara guardada.', variant: 'success');
    }

    public function toggle(int $id): void
    {
        $chamber = Chamber::query()->findOrFail($id);
        Gate::authorize('update', $chamber);

        $this->saveAudited($chamber, ['is_active' => ! $chamber->is_active], 'chamber');
    }

    public function render(): View
    {
        $totals = $this->searchedChambers()->toBase()
            ->selectRaw('count(*) as total, coalesce(sum(is_active), 0) as active')
            ->first();

        return view('livewire.admin.chambers', [
            'canManage' => Gate::allows('create', Chamber::class),
            'chambers' => $this->searchedChambers()
                ->with('branch')
                ->withCount('boxes')
                ->when($this->status === 'active', fn (Builder $query) => $query->where('is_active', true))
                ->when($this->status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
                ->orderBy('branch_id')->orderBy('name')
                ->paginate(15),
            'totals' => [
                'total' => (int) $totals->total,
                'active' => (int) $totals->active,
                'inactive' => (int) $totals->total - (int) $totals->active,
                'boxes' => Box::query()->whereIn('current_chamber_id', $this->searchedChambers()->select('id'))->count(),
            ],
            'branches' => Branch::query()->visibleTo(Auth::user())->orderBy('name')->get(),
            'hasFilters' => trim($this->search) !== '' || $this->branchFilter || $this->status !== 'all',
        ]);
    }
}
