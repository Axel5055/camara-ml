<?php

namespace App\Livewire\Inventory;

use App\Enums\BoxStatus;
use App\Livewire\Concerns\ReportsInventoryErrors;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\Entry;
use App\Services\Inventory\CorrectEntry;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Detalle de entrada')]
class EntryDetail extends Component
{
    use ReportsInventoryErrors, WithPagination;

    public Entry $entry;

    public string $search = '';

    public string $editDate = '';

    public string $editNotes = '';

    public string $editReason = '';

    /** @var array<int|string, array{boxes_count: int|string, weight_per_box_kg: string, unit_cost_mxn: string}> */
    public array $editItems = [];

    public function mount(Entry $entry): void
    {
        Gate::authorize('view', $entry);

        $this->entry = $entry;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->reset('search');
        $this->resetPage();
    }

    public function openEdit(): void
    {
        Gate::authorize('update', $this->entry);

        $this->editDate = $this->entry->occurred_at->format('Y-m-d\TH:i');
        $this->editNotes = (string) $this->entry->notes;
        $this->editReason = '';
        $this->editItems = $this->entry->items()->orderBy('id')->get()->mapWithKeys(fn ($item): array => [
            $item->id => [
                'boxes_count' => $item->boxes_count,
                'weight_per_box_kg' => $item->weight_per_box_kg,
                'unit_cost_mxn' => $item->unit_cost_mxn,
            ],
        ])->all();

        $this->resetErrorBag();
        Flux::modal('edit-entry')->show();
    }

    public function saveEdit(CorrectEntry $correctEntry): void
    {
        Gate::authorize('update', $this->entry);

        $this->validate([
            'editDate' => ['required', 'date'],
            'editReason' => ['required', 'string', 'min:5', 'max:500'],
            'editNotes' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['editDate' => 'fecha', 'editReason' => 'motivo', 'editNotes' => 'observaciones']);

        $done = $this->attempt(fn () => $correctEntry->handle(
            Auth::user(), $this->entry, $this->editReason,
            $this->editDate === $this->entry->occurred_at->format('Y-m-d\TH:i') ? null : Date::parse($this->editDate),
            $this->editNotes, $this->editItems,
        ));

        if ($done) {
            $this->entry->refresh();
            $this->resetPage();
            Flux::modal('edit-entry')->close();
            Flux::toast(text: 'Entrada corregida.', variant: 'success');
        }
    }

    public function render(): View
    {
        $items = $this->entry->items()->with('product:id,name,code,category_id', 'product.category:id,name')->orderBy('id')->get();

        return view('livewire.inventory.entry-detail', [
            'chamber' => $this->entry->chamber()->with('branch:id,name')->first(),
            'registeredBy' => $this->entry->user()->first(['id', 'name']),
            'items' => $items,
            'stock' => Box::query()
                ->whereIn('entry_item_id', $items->modelKeys())
                ->whereNull('voided_at')
                ->where('status', BoxStatus::Available->value)
                ->selectRaw('count(*) as boxes, coalesce(sum(current_weight_kg), 0) as kg')
                ->first(),
            'boxes' => Box::query()
                ->whereIn('entry_item_id', $items->modelKeys())
                ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($match) => $match
                    ->where('barcode', 'like', '%'.trim($this->search).'%')
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', '%'.trim($this->search).'%')->orWhere('code', 'like', '%'.trim($this->search).'%'))))
                ->with(['product:id,name', 'currentChamber:id,name'])
                ->orderBy('id')
                ->paginate(25),
            'showCosts' => Gate::allows('view-costs'),
            'canEdit' => Gate::allows('update', $this->entry),
            'corrections' => AuditLog::query()
                ->where('event', 'entry.corrected')
                ->where('auditable_type', $this->entry->getMorphClass())
                ->where('auditable_id', $this->entry->getKey())
                ->with('user:id,name')
                ->orderByDesc('id')
                ->get(),
        ]);
    }
}
