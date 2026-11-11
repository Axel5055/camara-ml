<?php

namespace App\Livewire\Admin;

use App\Actions\Users\AssignChambers;
use App\Actions\Users\AssignManagerScope;
use App\Enums\UserRole;
use App\Livewire\Concerns\ReportsInventoryErrors;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Usuarios')]
class Users extends Component
{
    use ReportsInventoryErrors, WithPagination;

    public string $search = '';

    /** all | a UserRole value */
    public string $roleFilter = 'all';

    /** all | active | inactive */
    public string $status = 'all';

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'warehouse';

    public bool $is_active = true;

    public ?int $branch_id = null;

    /** @var array<int, int|string> */
    public array $chamberIds = [];

    /** @var array<int, int|string> Branches a manager sees in full. */
    public array $branchIds = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'roleFilter', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'roleFilter', 'status');
        $this->resetPage();
    }

    /**
     * Users matching the search (role and status are applied separately so the cards stay complete).
     *
     * @return Builder<User>
     */
    private function searchedUsers(): Builder
    {
        $search = trim($this->search);

        return User::query()->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->where('name', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%")));
    }

    public function create(): void
    {
        Gate::authorize('create', User::class);

        $this->reset('editingId', 'name', 'email', 'password', 'branch_id', 'chamberIds', 'branchIds');
        $this->role = UserRole::Warehouse->value;
        $this->is_active = true;
        $this->resetValidation();
        Flux::modal('user-form')->show();
    }

    public function edit(int $id): void
    {
        $user = User::query()->with(['chambers', 'managedBranches'])->findOrFail($id);
        Gate::authorize('update', $user);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->role->value;
        $this->is_active = $user->is_active;
        $this->branch_id = $user->branch_id;
        $this->chamberIds = $user->chambers->pluck('id')->all();
        $this->branchIds = $user->managedBranches->pluck('id')->all();
        $this->resetValidation();
        Flux::modal('user-form')->show();
    }

    public function updatedBranchId(): void
    {
        $this->chamberIds = [];
    }

    public function updatedRole(): void
    {
        $this->reset('branch_id', 'chamberIds', 'branchIds');
    }

    public function save(AssignChambers $assignChambers, AssignManagerScope $assignManagerScope): void
    {
        $user = $this->editingId ? User::query()->findOrFail($this->editingId) : new User;
        Gate::authorize($user->exists ? 'update' : 'create', $user->exists ? $user : User::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => [$user->exists ? 'nullable' : 'required', 'string', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['boolean'],
            'branch_id' => [Rule::requiredIf($this->role === UserRole::Warehouse->value), 'nullable', Rule::exists('branches', 'id')],
        ]);

        if ($user->is(Auth::user()) && ($validated['role'] !== $user->role->value || ! $validated['is_active'])) {
            $this->addError('operation', 'No puedes cambiar tu propio rol ni desactivar tu propia cuenta.');

            return;
        }

        $saved = $this->attempt(function () use ($user, $validated, $assignChambers, $assignManagerScope): void {
            DB::transaction(function () use ($user, $validated, $assignChambers, $assignManagerScope): void {
                $isNew = ! $user->exists;
                $old = $isNew ? null : $user->only(['name', 'email', 'role', 'is_active', 'branch_id']);

                $user->forceFill([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'role' => $validated['role'],
                    'is_active' => $validated['is_active'],
                    'branch_id' => $validated['role'] === UserRole::Warehouse->value ? $validated['branch_id'] : null,
                ]);

                if (($validated['password'] ?? '') !== '') {
                    $user->password = $validated['password'];
                }

                if ($isNew) {
                    $user->email_verified_at = now();
                }

                $user->save();

                if ($user->role === UserRole::Warehouse) {
                    $assignChambers->handle(Auth::user(), $user, array_map('intval', $this->chamberIds));
                    $user->managedBranches()->sync([]);
                } elseif ($user->role === UserRole::Manager) {
                    $assignManagerScope->handle(Auth::user(), $user, array_map('intval', $this->branchIds), array_map('intval', $this->chamberIds));
                } else {
                    $user->chambers()->sync([]);
                    $user->managedBranches()->sync([]);
                }

                AuditLog::record(Auth::user(), $isNew ? 'user.created' : 'user.updated', $user, $old, [
                    'name' => $user->name, 'email' => $user->email, 'role' => $user->role->value,
                    'is_active' => $user->is_active, 'branch_id' => $user->branch_id, 'chambers' => $user->chambers()->pluck('chambers.id')->all(),
                    'managed_branches' => $user->managedBranches()->pluck('branches.id')->all(),
                    'password_changed' => ($validated['password'] ?? '') !== '',
                ]);
            });
        });

        if ($saved) {
            Flux::modal('user-form')->close();
            Flux::toast(text: 'Usuario guardado.', variant: 'success');
        }
    }

    public function render(): View
    {
        $byRole = $this->searchedUsers()->toBase()
            ->selectRaw('role, count(*) as total, coalesce(sum(is_active), 0) as active')
            ->groupBy('role')
            ->get()
            ->keyBy('role');

        $countOf = fn (UserRole $role): int => (int) ($byRole[$role->value]->total ?? 0);

        return view('livewire.admin.users', [
            'roles' => UserRole::cases(),
            'users' => $this->searchedUsers()
                ->with(['branch', 'chambers', 'managedBranches'])
                ->when(UserRole::tryFrom($this->roleFilter), fn (Builder $query, UserRole $role) => $query->where('role', $role))
                ->when($this->status === 'active', fn (Builder $query) => $query->where('is_active', true))
                ->when($this->status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
                ->orderBy('name')
                ->paginate(15),
            'totals' => [
                'total' => (int) $byRole->sum('total'),
                'super_admin' => $countOf(UserRole::SuperAdmin),
                'admin' => $countOf(UserRole::Admin),
                'manager' => $countOf(UserRole::Manager),
                'warehouse' => $countOf(UserRole::Warehouse),
                'inactive' => (int) $byRole->sum('total') - (int) $byRole->sum('active'),
            ],
            'branches' => Branch::query()->orderBy('name')->get(),
            'branchChambers' => $this->branch_id
                ? Chamber::query()->where('branch_id', $this->branch_id)->orderBy('name')->get()
                : collect(),
            'allChambers' => $this->role === UserRole::Manager->value
                ? Chamber::query()->with('branch')->orderBy('branch_id')->orderBy('name')->get()
                : collect(),
            'hasFilters' => trim($this->search) !== '' || $this->roleFilter !== 'all' || $this->status !== 'all',
        ]);
    }
}
