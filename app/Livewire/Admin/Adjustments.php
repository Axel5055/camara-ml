<?php

namespace App\Livewire\Admin;

use App\Enums\AdjustmentType;
use App\Enums\BoxStatus;
use App\Livewire\Concerns\ReportsInventoryErrors;
use App\Livewire\Concerns\RequiresSuperAdmin;
use App\Models\Box;
use App\Models\Chamber;
use App\Models\InventoryAdjustment;
use App\Models\User;
use App\Services\Inventory\AdjustInventory;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Ajustes de inventario')]
class Adjustments extends Component
{
    use ReportsInventoryErrors, RequiresSuperAdmin, WithPagination;

    public string $search = '';

    /** all | an AdjustmentType value */
    public string $typeFilter = 'all';

    public ?string $from = null;

    public ?string $to = null;

    public string $barcode = '';

    public string $type = 'weight_correction';

    public string $newWeight = '';

    public string $newStatus = '';

    public ?int $chamberId = null;

    public string $reason = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'typeFilter', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'typeFilter', 'from', 'to');
        $this->resetPage();
    }

    /**
     * Adjustments matching the search and dates (the type filter is applied separately so the cards stay complete).
     *
     * @return Builder<InventoryAdjustment>
     */
    private function searchedAdjustments(): Builder
    {
        $search = trim($this->search);

        return InventoryAdjustment::query()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('reason', 'like', "%{$search}%")
                ->orWhereHas('box', fn (Builder $box) => $box->where('barcode', 'like', '%'.strtoupper($search).'%'))))
            ->when($this->from, fn (Builder $query, string $from) => $query->where('occurred_at', '>=', $from.' 00:00:00'))
            ->when($this->to, fn (Builder $query, string $to) => $query->where('occurred_at', '<=', $to.' 23:59:59'));
    }

    public function create(): void
    {
        $this->reset('barcode', 'newWeight', 'newStatus', 'chamberId', 'reason');
        $this->type = AdjustmentType::WeightCorrection->value;
        $this->resetValidation();
        Flux::modal('adjustment-form')->show();
    }

    /**
     * The box typed in the form, so the administrator sees what is being adjusted.
     */
    private function lookedUpBox(): ?Box
    {
        return $this->barcode === '' ? null : Box::query()->with(['product:id,name', 'currentChamber:id,name'])
            ->where('barcode', strtoupper(trim($this->barcode)))->first();
    }

    public function save(AdjustInventory $adjustInventory): void
    {
        $this->validate([
            'barcode' => ['required', 'string'],
            'type' => ['required', Rule::enum(AdjustmentType::class)],
            'newWeight' => ['nullable', 'numeric', 'min:0'],
            'newStatus' => ['nullable', Rule::enum(BoxStatus::class)],
            'chamberId' => ['nullable', 'exists:chambers,id'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $box = $this->lookedUpBox();

        if ($box === null) {
            $this->addError('barcode', 'No existe una caja con ese código.');

            return;
        }

        $done = $this->attempt(fn () => $adjustInventory->handle(
            Auth::user(),
            $box,
            AdjustmentType::from($this->type),
            $this->reason,
            $this->newWeight === '' ? null : $this->newWeight,
            BoxStatus::tryFrom($this->newStatus),
            $this->chamberId ? Chamber::query()->findOrFail($this->chamberId) : null,
        ));

        if ($done) {
            Flux::modal('adjustment-form')->close();
            Flux::toast(text: 'Ajuste registrado.', variant: 'success');
        }
    }

    public function render(): View
    {
        $byType = $this->searchedAdjustments()->toBase()
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $countOf = fn (AdjustmentType ...$types): int => (int) collect($types)->sum(fn (AdjustmentType $type): int => (int) ($byType[$type->value] ?? 0));

        return view('livewire.admin.adjustments', [
            'box' => $this->lookedUpBox(),
            'adjustments' => $this->searchedAdjustments()
                ->with(['box:id,barcode'])
                ->when(AdjustmentType::tryFrom($this->typeFilter), fn (Builder $query, AdjustmentType $type) => $query->where('type', $type))
                ->orderByDesc('occurred_at')->orderByDesc('id')
                ->paginate(20),
            'totals' => [
                'total' => (int) $byType->sum(),
                'weight' => $countOf(AdjustmentType::WeightCorrection),
                'losses' => $countOf(AdjustmentType::Lost, AdjustmentType::Damaged),
                'found' => $countOf(AdjustmentType::Found, AdjustmentType::StatusFix),
            ],
            'chambers' => Chamber::query()->with('branch:id,name')->where('is_active', true)->orderBy('name')->get(),
            'types' => AdjustmentType::cases(),
            'statuses' => BoxStatus::cases(),
            'users' => User::query()->pluck('name', 'id'),
            'hasFilters' => trim($this->search) !== '' || $this->typeFilter !== 'all' || $this->from || $this->to,
        ]);
    }
}
