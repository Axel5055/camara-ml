<?php

namespace App\Livewire\Inventory;

use App\Enums\BoxStatus;
use App\Enums\TransferStatus;
use App\Livewire\Concerns\HandlesTransferActions;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\Chamber;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Services\Inventory\CorrectTransfer;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Detalle de transferencia')]
class TransferDetail extends Component
{
    use HandlesTransferActions, WithPagination;

    public Transfer $transfer;

    public string $search = '';

    public string $editRequester = '';

    public string $editNotes = '';

    public string $editDate = '';

    public string $editDestChamberId = '';

    public string $editReason = '';

    public string $addBarcode = '';

    /** @var array<int, int|string> */
    public array $removeIds = [];

    /** @var array<int, int|string> */
    public array $addIds = [];

    public function mount(Transfer $transfer): void
    {
        Gate::authorize('view', $transfer);

        $this->transfer = $transfer;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openEdit(): void
    {
        Gate::authorize('correct', $this->transfer);

        $exit = $this->transfer->exit()->firstOrFail();

        $this->editRequester = $exit->requester_name;
        $this->editNotes = (string) $exit->notes;
        $this->editDate = $this->transfer->dispatched_at->format('Y-m-d\TH:i');
        $this->editDestChamberId = (string) $this->transfer->dest_chamber_id;
        $this->editReason = '';
        $this->addBarcode = '';
        $this->removeIds = [];
        $this->addIds = [];

        $this->resetErrorBag();
        Flux::modal('edit-transfer')->show();
    }

    public function addBox(): void
    {
        Gate::authorize('correct', $this->transfer);

        $this->resetErrorBag('addBarcode');
        $barcode = mb_strtoupper(trim($this->addBarcode));

        if ($barcode === '') {
            return;
        }

        $box = Box::query()->where('barcode', $barcode)->first();

        if ($box === null) {
            $this->addError('addBarcode', "No existe una caja con el código {$barcode}.");

            return;
        }

        if ($box->status !== BoxStatus::Available || $box->current_chamber_id !== $this->transfer->origin_chamber_id) {
            $this->addError('addBarcode', "La caja {$barcode} no está disponible en la cámara de origen de esta transferencia.");

            return;
        }

        if (! in_array($box->id, $this->addIds, true)) {
            $this->addIds[] = $box->id;
        }

        $this->addBarcode = '';
    }

    public function removePendingAdd(int $boxId): void
    {
        $this->addIds = array_values(array_filter($this->addIds, fn (int|string $id): bool => (int) $id !== $boxId));
    }

    public function saveEdit(CorrectTransfer $correctTransfer): void
    {
        Gate::authorize('correct', $this->transfer);

        $this->validate([
            'editRequester' => ['required', 'string', 'max:150'],
            'editDate' => ['required', 'date'],
            'editReason' => ['required', 'string', 'min:5', 'max:500'],
            'editNotes' => ['nullable', 'string', 'max:1000'],
            'editDestChamberId' => ['required', 'integer', 'exists:chambers,id'],
        ], attributes: ['editRequester' => 'solicitante', 'editDate' => 'fecha', 'editReason' => 'motivo', 'editNotes' => 'observaciones', 'editDestChamberId' => 'cámara destino']);

        $dest = Chamber::query()->findOrFail((int) $this->editDestChamberId);
        $sameDate = $this->editDate === $this->transfer->dispatched_at->format('Y-m-d\TH:i');

        $done = $this->attempt(fn () => $correctTransfer->handle(
            Auth::user(),
            $this->transfer,
            $this->editReason,
            $this->editRequester,
            $this->editNotes,
            $sameDate ? null : Date::parse($this->editDate),
            $dest,
            array_map('intval', $this->removeIds),
            array_map('intval', $this->addIds),
        ));

        if ($done) {
            $this->transfer->refresh();
            $this->resetPage();
            Flux::modal('edit-transfer')->close();
            Flux::toast(text: 'Transferencia corregida.', variant: 'success');
        }
    }

    public function render(): View
    {
        $this->transfer->refresh();

        $perProduct = DB::table('transfer_items')
            ->join('boxes', 'boxes.id', '=', 'transfer_items.box_id')
            ->join('products', 'products.id', '=', 'boxes.product_id')
            ->where('transfer_items.transfer_id', $this->transfer->id)
            ->whereNull('transfer_items.removed_at')
            ->groupBy('products.id', 'products.name')
            ->orderBy('products.name')
            ->selectRaw('products.name as product, count(*) as boxes, sum(transfer_items.expected_weight_kg) as kg, min(boxes.unit_cost_mxn) as min_unit_cost, max(boxes.unit_cost_mxn) as max_unit_cost')
            ->get();

        $exit = $this->transfer->exit()->first();
        $canCorrect = Gate::allows('correct', $this->transfer);
        $inTransit = $this->transfer->status === TransferStatus::InTransit;
        $editData = [];

        if ($canCorrect) {
            $editData = [
                'editableItems' => $inTransit
                    ? TransferItem::query()->where('transfer_id', $this->transfer->id)->with('box:id,barcode,product_id,unit_cost_mxn', 'box.product:id,name')->orderBy('id')->get()
                    : collect(),
                'destChambers' => Chamber::query()->with('branch:id,name')
                    ->where(fn (Builder $query) => $query->where('is_active', true)->whereHas('branch', fn (Builder $branch) => $branch->where('is_active', true))->orWhere('id', $this->transfer->dest_chamber_id))
                    ->where('id', '!=', $this->transfer->origin_chamber_id)
                    ->orderBy('branch_id')->orderBy('name')->get(),
                'pendingBoxes' => Box::query()->with('product:id,name')->whereIn('id', array_map('intval', $this->addIds))->get(['id', 'barcode', 'product_id', 'current_weight_kg', 'unit_cost_mxn']),
            ];
        }

        return view('livewire.inventory.transfer-detail', [
            'exit' => $exit,
            'originChamber' => $this->transfer->originChamber()->with('branch:id,name')->first(),
            'destChamber' => $this->transfer->destChamber()->with('branch:id,name')->first(),
            'registeredBy' => $exit->user()->first(['id', 'name']),
            'receiver' => $this->transfer->receiver()->first(['id', 'name']),
            'canceller' => $this->transfer->canceller()->first(['id', 'name']),
            'showCosts' => Gate::allows('view-costs'),
            'perProduct' => $perProduct,
            'totalBoxes' => (int) $perProduct->sum('boxes'),
            'totalKg' => (float) $perProduct->sum('kg'),
            'items' => TransferItem::query()
                ->where('transfer_id', $this->transfer->id)
                ->when(trim($this->search) !== '', fn ($query) => $query->whereHas('box', fn ($box) => $box
                    ->where('barcode', 'like', '%'.trim($this->search).'%')
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', '%'.trim($this->search).'%'))))
                ->with(['box:id,barcode,product_id,unit_cost_mxn', 'box.product:id,name'])
                ->orderBy('id')
                ->paginate(25),
            'incidents' => $this->transfer->incidents()->with('box:id,barcode', 'reporter:id,name')->orderBy('id')->get(),
            'acting' => $this->actingTransfer(),
            'canReceive' => Gate::allows('receive', $this->transfer),
            'canCancel' => Gate::allows('cancel', $this->transfer),
            'canCorrect' => $canCorrect,
            'inTransit' => $inTransit,
            'corrections' => AuditLog::query()
                ->where('event', 'transfer.corrected')
                ->where('auditable_type', $this->transfer->getMorphClass())
                ->where('auditable_id', $this->transfer->getKey())
                ->with('user:id,name')
                ->orderByDesc('id')
                ->get(),
            ...$editData,
        ]);
    }
}
