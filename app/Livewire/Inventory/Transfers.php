<?php

namespace App\Livewire\Inventory;

use App\Enums\TransferStatus;
use App\Livewire\Concerns\HandlesTransferActions;
use App\Livewire\Concerns\InteractsWithChambers;
use App\Models\Transfer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Transferencias')]
class Transfers extends Component
{
    use HandlesTransferActions, InteractsWithChambers, WithPagination;

    /** all | incoming | outgoing */
    public string $direction = 'all';

    #[Url(as: 'estado')]
    public string $status = 'all';

    public string $search = '';

    public ?string $from = null;

    public ?string $to = null;

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

    public function render(): View
    {
        $byStatus = $this->visibleTransfers()->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('livewire.inventory.transfers', [
            'transfers' => $this->visibleTransfers()
                ->with(['originChamber:id,name,branch_id', 'originChamber.branch:id,name', 'destChamber:id,name,branch_id', 'destChamber.branch:id,name', 'exit:id,reference,requester_name'])
                ->select('transfers.*')
                ->addSelect([
                    'min_unit_cost' => DB::table('transfer_items')->join('boxes', 'boxes.id', '=', 'transfer_items.box_id')
                        ->whereColumn('transfer_items.transfer_id', 'transfers.id')->whereNull('transfer_items.removed_at')->selectRaw('min(boxes.unit_cost_mxn)'),
                    'max_unit_cost' => DB::table('transfer_items')->join('boxes', 'boxes.id', '=', 'transfer_items.box_id')
                        ->whereColumn('transfer_items.transfer_id', 'transfers.id')->whereNull('transfer_items.removed_at')->selectRaw('max(boxes.unit_cost_mxn)'),
                ])
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
            'acting' => $this->actingTransfer(),
            'user' => Auth::user(),
            'hasFilters' => $this->direction !== 'all' || $this->status !== 'all' || trim($this->search) !== '' || $this->from || $this->to,
        ]);
    }
}
