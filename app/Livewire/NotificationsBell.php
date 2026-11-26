<?php

namespace App\Livewire;

use App\Enums\EntryRequestStatus;
use App\Enums\ExitRequestStatus;
use App\Enums\TransferStatus;
use App\Enums\UserRole;
use App\Models\Chamber;
use App\Models\EntryRequest;
use App\Models\ExitRequest;
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
        if ($user->role === UserRole::Requester) {
            return new Collection;
        }

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

    /**
     * Exit requests worth a look: warehouse users and super administrators see the ones waiting for their
     * decision; requesters see their own pending ones and those decided in the last few days. Administrators
     * and managers only read them in the list.
     *
     * @return Collection<int, ExitRequest>
     */
    private function exitRequests(User $user): Collection
    {
        if (! in_array($user->role, [UserRole::Requester, UserRole::Warehouse, UserRole::SuperAdmin], true)) {
            return new Collection;
        }

        if ($user->role === UserRole::Requester) {
            return $this->ownExitRequests($user);
        }

        return ExitRequest::query()->incomingFor($user)->with(['branch:id,name', 'chamber:id,name'])
            ->where('status', ExitRequestStatus::Pending)
            ->orderByDesc('created_at')->limit(15)->get();
    }

    /**
     * What the user asked for: still pending, or answered in the last few days.
     *
     * @return Collection<int, ExitRequest>
     */
    private function ownExitRequests(User $user): Collection
    {
        return ExitRequest::query()->where('user_id', $user->id)->with(['branch:id,name', 'chamber:id,name'])
            ->where(fn (Builder $inner) => $inner
                ->where('status', ExitRequestStatus::Pending)
                ->orWhere(fn (Builder $decided) => $decided->whereIn('status', [ExitRequestStatus::Approved, ExitRequestStatus::Rejected])->where('decided_at', '>=', now()->subDays(3))))
            ->orderByDesc('created_at')->limit(15)->get();
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

        $isRequester = $user->role === UserRole::Requester;
        $exitRequests = $this->exitRequests($user);
        $exitRequestsToAttend = $isRequester
            ? $exitRequests->where('status', '!=', ExitRequestStatus::Pending)->count()
            : $exitRequests->count();

        // Warehouse users also ask for product: their own requests are listed apart.
        $myExitRequests = $isWarehouse ? $this->ownExitRequests($user) : new Collection;
        $myAnswered = $myExitRequests->where('status', '!=', ExitRequestStatus::Pending)->count();

        return view('livewire.notifications-bell', [
            'count' => $low->count() + $transfers->count() + $requestsToAttend + $exitRequestsToAttend + $myAnswered,
            'myExitRequests' => $myExitRequests,
            'isRequester' => $isRequester,
            'showExitRequests' => in_array($user->role, [UserRole::Requester, UserRole::Warehouse, UserRole::SuperAdmin], true),
            'exitRequests' => $exitRequests,
            'exitRequestsToAttend' => $exitRequestsToAttend,
            'requests' => $requests,
            'requestsToAttend' => $requestsToAttend,
            'pendingCount' => $exitRequestsToAttend + $myAnswered + $requestsToAttend + $transfers->count(),
            'items' => $low,
            'transfers' => $transfers,
            'chamberIds' => $chamberIds,
            'canSeeTransfers' => $isWarehouse,
            'threshold' => LowStockAlerts::THRESHOLD,
        ]);
    }
}
