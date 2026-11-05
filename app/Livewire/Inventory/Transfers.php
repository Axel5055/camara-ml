<?php

namespace App\Livewire\Inventory;

use App\Enums\TransferStatus;
use App\Livewire\Concerns\InteractsWithChambers;
use App\Livewire\Concerns\ReportsInventoryErrors;
use App\Models\Transfer;
use App\Services\Inventory\CancelTransfer;
use App\Services\Inventory\ReceiveTransfer;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Transferencias')]
class Transfers extends Component
{
    use InteractsWithChambers, ReportsInventoryErrors, WithPagination;

    /** all | incoming | outgoing */
    public string $direction = 'all';

    #[Url(as: 'estado')]
    public string $status = 'all';

    public string $search = '';

    public ?string $from = null;

    public ?string $to = null;

    public ?int $actingId = null;

    /** @var array<int, int|string> box ids physically received */
    public array $received = [];

    /** @var array<int|string, string|null> measured weights by box id */
    public array $weights = [];

    public string $reason = '';

    public string $receiveNotes = '';

    public ?int $detailId = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['direction', 'status', 'search', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('direction', 'status', 'search', 'from', 'to');
        $this->resetPage();
    }

    /**
     * Transfers the user may see, narrowed by every filter except the status (so the status cards stay complete).
     *
     * @return Builder<Transfer>
     */
    private function visibleTransfers(): Builder
    {
        $visible = $this->visibleChamberIds();
        $mine = $visible ?? [];
        $search = trim($this->search);

        return Transfer::query()
            ->when($visible !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereIn('origin_chamber_id', $visible)->orWhereIn('dest_chamber_id', $visible)))
            ->when($this->direction === 'incoming' && $visible !== null, fn (Builder $query) => $query->whereIn('dest_chamber_id', $mine))
            ->when($this->direction === 'outgoing' && $visible !== null, fn (Builder $query) => $query->whereIn('origin_chamber_id', $mine))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('reference', 'like', "%{$search}%")
                ->orWhereHas('exit', fn (Builder $exit) => $exit->where('reference', 'like', "%{$search}%")->orWhere('requester_name', 'like', "%{$search}%"))))
            ->when($this->from, fn (Builder $query, string $from) => $query->where('dispatched_at', '>=', $from.' 00:00:00'))
            ->when($this->to, fn (Builder $query, string $to) => $query->where('dispatched_at', '<=', $to.' 23:59:59'));
    }

    public function askReceive(int $id): void
    {
        $transfer = Transfer::query()->with('items')->findOrFail($id);
        Gate::authorize('receive', $transfer);

        $this->actingId = $transfer->id;
        $this->received = $transfer->items->pluck('box_id')->all();
        $this->weights = [];
        $this->receiveNotes = '';
        $this->resetErrorBag();
        Flux::modal('receive-transfer')->show();
    }

    public function receive(ReceiveTransfer $receiveTransfer): void
    {
        $transfer = Transfer::query()->findOrFail($this->actingId);
        Gate::authorize('receive', $transfer);

        $weights = array_filter($this->weights, fn ($weight): bool => $weight !== '' && $weight !== null);

        $done = $this->attempt(fn () => $receiveTransfer->handle(Auth::user(), $transfer, $this->received, $weights, $this->receiveNotes));

        if ($done) {
            Flux::modal('receive-transfer')->close();
            Flux::toast(text: 'Transferencia recibida.', variant: 'success');
        }
    }

    public function showDetail(int $id): void
    {
        Gate::authorize('view', Transfer::query()->findOrFail($id));

        $this->detailId = $id;
        Flux::modal('transfer-detail')->show();
    }

    public function askCancel(int $id): void
    {
        Gate::authorize('cancel', Transfer::query()->findOrFail($id));

        $this->actingId = $id;
        $this->reason = '';
        $this->resetErrorBag();
        Flux::modal('cancel-transfer')->show();
    }

    public function cancel(CancelTransfer $cancelTransfer): void
    {
        $transfer = Transfer::query()->with('exit')->findOrFail($this->actingId);
        Gate::authorize('cancel', $transfer);

        $done = $this->attempt(fn () => $cancelTransfer->handle(Auth::user(), $transfer, $this->reason));

        if ($done) {
            Flux::modal('cancel-transfer')->close();
            Flux::toast(text: 'Transferencia cancelada.', variant: 'success');
        }
    }

    public function render(): View
    {
        $acting = $this->actingId
            ? Transfer::query()->with('originChamber:id,name', 'destChamber:id,name', 'items.box:id,barcode,current_weight_kg,product_id', 'items.box.product:id,name')->find($this->actingId)
            : null;

        $detail = null;

        if ($this->detailId) {
            $detail = Transfer::query()
                ->with([
                    'originChamber.branch:id,name', 'destChamber.branch:id,name', 'exit.user:id,name', 'receiver:id,name', 'canceller:id,name',
                    'items.box:id,barcode,current_weight_kg,product_id', 'items.box.product:id,name', 'incidents.box:id,barcode', 'incidents.reporter:id,name', 'incidents.resolver:id,name',
                ])
                ->find($this->detailId);

            if ($detail !== null && Gate::denies('view', $detail)) {
                $detail = null;
            }
        }

        $byStatus = $this->visibleTransfers()->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('livewire.inventory.transfers', [
            'transfers' => $this->visibleTransfers()
                ->with(['originChamber:id,name,branch_id', 'originChamber.branch:id,name', 'destChamber:id,name,branch_id', 'destChamber.branch:id,name', 'exit:id,reference,requester_name'])
                ->withCount('items')
                ->withCount('incidents')
                ->withCount(['incidents as open_incidents_count' => fn (Builder $query) => $query->where('status', 'open')])
                ->when(TransferStatus::tryFrom($this->status), fn (Builder $query, TransferStatus $status) => $query->where('status', $status))
                ->orderByDesc('dispatched_at')->orderByDesc('id')
                ->paginate(20),
            'counts' => [
                'in_transit' => (int) ($byStatus[TransferStatus::InTransit->value] ?? 0),
                'received' => (int) ($byStatus[TransferStatus::Received->value] ?? 0),
                'cancelled' => (int) ($byStatus[TransferStatus::Cancelled->value] ?? 0),
                'incidents' => $this->visibleTransfers()->whereHas('incidents', fn (Builder $query) => $query->where('status', 'open'))->count(),
            ],
            'acting' => $acting,
            'detail' => $detail,
            'user' => Auth::user(),
            'hasFilters' => $this->direction !== 'all' || $this->status !== 'all' || trim($this->search) !== '' || $this->from || $this->to,
        ]);
    }
}
