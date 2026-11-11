<?php

namespace App\Policies;

use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\User;

class TransferPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transfer $transfer): bool
    {
        return $user->canViewChamber($transfer->originChamber) || $user->canViewChamber($transfer->destChamber);
    }

    /**
     * Super administrators, administrators and (within their scope) managers correct a transfer: boxes,
     * destination and date while it is in transit, requester and notes also once received.
     */
    public function correct(User $user, Transfer $transfer): bool
    {
        return $user->canCorrectRecordsOf($transfer->originChamber, $transfer->destChamber)
            && in_array($transfer->status, [TransferStatus::InTransit, TransferStatus::Received], true);
    }

    public function receive(User $user, Transfer $transfer): bool
    {
        return $transfer->status === TransferStatus::InTransit && $user->canAccessChamber($transfer->destChamber);
    }

    /**
     * Super administrators, administrators and (within their scope) managers cancel a transfer, whether it is
     * still in transit or was already received (to correct a mistake). Warehouse users ask them to do it.
     */
    public function cancel(User $user, Transfer $transfer): bool
    {
        return $user->canCorrectRecordsOf($transfer->originChamber, $transfer->destChamber)
            && in_array($transfer->status, [TransferStatus::InTransit, TransferStatus::Received], true);
    }
}
