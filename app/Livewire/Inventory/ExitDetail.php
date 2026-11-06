<?php

namespace App\Livewire\Inventory;

use App\Enums\BoxStatus;
use App\Enums\DocumentStatus;
use App\Enums\ExitItemKind;
use App\Livewire\Concerns\ReportsInventoryErrors;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\Branch;
use App\Models\ExitItem;
use App\Models\StockExit;
use App\Models\User;
use App\Services\Inventory\CorrectExit;
use App\Services\Inventory\ReverseExit;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Detalle de salida')]
class ExitDetail extends Component
{
    use ReportsInventoryErrors, WithPagination;

    public StockExit $stockExit;

    public string $search = '';

    public string $editRequester = '';

    public string $editNotes = '';

    public string $editDate = '';

    public string $editBranchId = '';

    public string $editReason = '';

    public string $reverseReason = '';

    public string $addBarcode = '';

    /** @var array<int, int|string> */
    public array $removeIds = [];

    /** @var array<int, int|string> */
    public array $addIds = [];

    public function mount(StockExit $exit): void
    {
        Gate::authorize('view', $exit);

        $this->stockExit = $exit;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openReverse(): void
    {
        Gate::authorize('reverse', $this->stockExit);

        $this->reverseReason = '';
        $this->resetErrorBag();
        Flux::modal('reverse-exit')->show();
    }

    public function reverse(ReverseExit $reverseExit): void
    {
        Gate::authorize('reverse', $this->stockExit);

        $this->validate(['reverseReason' => ['required', 'string', 'min:5', 'max:500']], attributes: ['reverseReason' => 'motivo']);

        $done = $this->attempt(fn () => $reverseExit->handle(Auth::user(), $this->stockExit, $this->reverseReason));

        if ($done) {
            $this->stockExit->refresh();
            $this->resetPage();
            Flux::modal('reverse-exit')->close();
            Flux::toast(text: 'Salida revertida.', variant: 'success');
        }
    }

    public function openEdit(): void
    {
        Gate::authorize('update', $this->stockExit);

        $this->editRequester = $this->stockExit->requester_name;
        $this->editNotes = (string) $this->stockExit->notes;
        $this->editDate = $this->stockExit->occurred_at->format('Y-m-d\TH:i');
        $this->editBranchId = (string) $this->stockExit->dest_branch_id;
        $this->editReason = '';
        $this->addBarcode = '';
        $this->removeIds = [];
        $this->addIds = [];

        $this->resetErrorBag();
        Flux::modal('edit-exit')->show();
    }

    public function addBox(): void
    {
        Gate::authorize('update', $this->stockExit);

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

        if ($box->status !== BoxStatus::Available || $box->current_chamber_id !== $this->stockExit->origin_chamber_id) {
            $this->addError('addBarcode', "La caja {$barcode} no está disponible en la cámara de origen de esta salida.");

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

    public function saveEdit(CorrectExit $correctExit): void
    {
        Gate::authorize('update', $this->stockExit);

        $this->validate([
            'editRequester' => ['required', 'string', 'max:150'],
            'editDate' => ['required', 'date'],
            'editReason' => ['required', 'string', 'min:5', 'max:500'],
            'editNotes' => ['nullable', 'string', 'max:1000'],
            'editBranchId' => ['required', 'integer', 'exists:branches,id'],
        ], attributes: ['editRequester' => 'solicitante', 'editDate' => 'fecha', 'editReason' => 'motivo', 'editNotes' => 'observaciones', 'editBranchId' => 'destino']);

        $branch = Branch::query()->findOrFail((int) $this->editBranchId);

        $done = $this->attempt(fn () => $correctExit->handle(
            Auth::user(),
            $this->stockExit,
            $this->editReason,
            $this->editRequester,
            $this->editNotes,
            $this->editDate === $this->stockExit->occurred_at->format('Y-m-d\TH:i') ? null : Date::parse($this->editDate),
            $branch,
            array_map('intval', $this->removeIds),
            array_map('intval', $this->addIds),
        ));

        if ($done) {
            $this->stockExit->refresh();
            $this->resetPage();
            Flux::modal('edit-exit')->close();
            Flux::toast(text: 'Salida corregida.', variant: 'success');
        }
    }

    public function render(): View
    {
        $showCosts = Gate::allows('view-costs');
        $transfer = $this->stockExit->transfer()->first();
        $isConfirmed = $this->stockExit->status === DocumentStatus::Confirmed;

        $perProduct = DB::table('exit_items')
            ->join('boxes', 'boxes.id', '=', 'exit_items.box_id')
            ->join('products', 'products.id', '=', 'boxes.product_id')
            ->where('exit_items.exit_id', $this->stockExit->id)
            ->when($isConfirmed, fn ($query) => $query->where('exit_items.is_active', true))
            ->groupBy('products.id', 'products.name')
            ->orderBy('products.name')
            ->selectRaw('products.name as product, count(*) as boxes, sum(exit_items.weight_out_kg) as kg, sum(exit_items.cost_out_mxn) as cost, min(exit_items.unit_cost_snapshot_mxn) as min_unit_cost, max(exit_items.unit_cost_snapshot_mxn) as max_unit_cost')
            ->get();

        $canEdit = Gate::allows('update', $this->stockExit) && $isConfirmed;
        $editData = [];

        if ($canEdit) {
            $activeItems = ExitItem::query()
                ->where('exit_id', $this->stockExit->id)
                ->where('is_active', true)
                ->with(['box:id,barcode,product_id', 'box.product:id,name'])
                ->orderBy('id')
                ->get();

            $editData = [
                'editableItems' => $activeItems,
                'canEditBoxes' => $transfer === null && ! $activeItems->contains(fn (ExitItem $item): bool => $item->kind === ExitItemKind::Partial),
                'branches' => Branch::query()->where('is_active', true)->orWhere('id', $this->stockExit->dest_branch_id)->orderBy('name')->get(['id', 'name']),
                'pendingBoxes' => Box::query()->with('product:id,name')->whereIn('id', array_map('intval', $this->addIds))->get(['id', 'barcode', 'product_id', 'initial_weight_kg', 'current_weight_kg', 'unit_cost_mxn']),
            ];
        }

        return view('livewire.inventory.exit-detail', [
            'originChamber' => $this->stockExit->originChamber()->with('branch:id,name')->first(),
            'destBranch' => $this->stockExit->destBranch()->first(['id', 'name']),
            'destChamber' => $this->stockExit->destChamber()->first(['id', 'name']),
            'registeredBy' => $this->stockExit->user()->first(['id', 'name']),
            'reversedBy' => $this->stockExit->reversed_by ? User::query()->find($this->stockExit->reversed_by, ['id', 'name']) : null,
            'transfer' => $transfer,
            'perProduct' => $perProduct,
            'items' => ExitItem::query()
                ->where('exit_id', $this->stockExit->id)
                ->when(trim($this->search) !== '', fn ($query) => $query->whereHas('box', fn ($box) => $box
                    ->where('barcode', 'like', '%'.trim($this->search).'%')
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', '%'.trim($this->search).'%')->orWhere('code', 'like', '%'.trim($this->search).'%'))))
                ->with(['box:id,barcode,product_id,status,current_weight_kg', 'box.product:id,name'])
                ->orderBy('id')
                ->paginate(25),
            'showCosts' => $showCosts,
            'canEdit' => $canEdit,
            'canReverse' => $isConfirmed && Gate::allows('reverse', $this->stockExit),
            'corrections' => AuditLog::query()
                ->where('event', 'exit.corrected')
                ->where('auditable_type', $this->stockExit->getMorphClass())
                ->where('auditable_id', $this->stockExit->getKey())
                ->with('user:id,name')
                ->orderByDesc('id')
                ->get(),
            ...$editData,
        ]);
    }
}
