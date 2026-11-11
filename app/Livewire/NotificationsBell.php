<?php

namespace App\Livewire;

use App\Enums\EntryRequestStatus;
use App\Enums\TransferStatus;
use App\Enums\UserRole;
use App\Models\Chamber;
use App\Models\EntryRequest;
use App\Models\Transfer;
use App\Models\User;
use App\Services\Inventory\LowStockAlerts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NotificationsBell extends Component
{
    /**
     * Transfers in transit that involve the warehouse user's chambers: the ones arriving (the destination has
     * to confirm them) first, then their own shipments still waiting to be received.
     *
     * @param  array<int, int>  $chamberIds
     * @return Collection<int, Transfer>
     */
    private function pendingTransfers(array $chamberIds): Collection
    {
        return Transfer::query()
            ->where('status', TransferStatus::InTransit)
            ->where(fn (Builder $query) => $query
                ->whereIn('dest_chamber_id', $chamberIds)->orWhereIn('origin_chamber_id', $chamberIds))
            ->with(['originChamber:id,name,branch_id', 'originChamber.branch:id,name', 'destChamber:id,name,branch_id', 'destChamber.branch:id,name', 'exit:id,reference,requester_name'])
            ->withCount('items')
            ->orderBy('dispatched_at')
            ->get()
            ->sortBy(fn (Transfer $transfer): int => in_array($transfer->dest_chamber_id, $chamberIds, true) ? 0 : 1)
            ->values();
    }

    /**
     * Entry requests worth a look: administrators see the ones waiting for a decision; warehouse users see
     * their own pending ones and those resolved in the last few days.
     *
     * @return Collection<int, EntryRequest>
     */
    private function entryRequests(User $user): Collection
    {
        $query = EntryRequest::query()->with(['chamber:id,name,branch_id', 'chamber.branch:id,name', 'user:id,name']);

        if ($user->role === UserRole::Warehouse) {
            $query->where('user_id', $user->id)->where(fn (Builder $inner) => $inner
                ->where('status', EntryRequestStatus::Pending)
                ->orWhere(fn (Builder $decided) => $decided->whereIn('status', [EntryRequestStatus::Approved, EntryRequestStatus::Rejected])->where('decided_at', '>=', now()->subDays(3))));
        } else {
            $query->where('status', EntryRequestStatus::Pending)
                ->when($user->isManager(), fn (Builder $inner) => $inner
                    ->whereIn('chamber_id', Chamber::query()->accessibleBy($user)->select('id')));
        }

        return $query->orderByDesc('created_at')->limit(15)->get();
    }

    public function render(LowStockAlerts $alerts): View
    {
        /** @var User $user */
        $user = Auth::user();
        $low = $alerts->for($user);

        $isWarehouse = $user->role === UserRole::Warehouse;
        $chamberIds = $isWarehouse ? Chamber::query()->accessibleBy($user)->pluck('id')->all() : [];
        $transfers = $isWarehouse ? $this->pendingTransfers($chamberIds) : new Collection;

        $requests = $this->entryRequests($user);
        $requestsToAttend = $isWarehouse
            ? $requests->where('status', '!=', EntryRequestStatus::Pending)->count()
            : $requests->count();

        return view('livewire.notifications-bell', [
            'count' => $low->count() + $transfers->count() + $requestsToAttend,
            'requests' => $requests,
            'requestsToAttend' => $requestsToAttend,
            'items' => $low,
            'transfers' => $transfers,
            'chamberIds' => $chamberIds,
            'canSeeTransfers' => $isWarehouse,
            'threshold' => LowStockAlerts::THRESHOLD,
        ]);
    }
}
