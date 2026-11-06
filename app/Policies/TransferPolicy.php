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
        return $user->canAccessChamber($transfer->originChamber) || $user->canAccessChamber($transfer->destChamber);
    }

    /**
     * Only a super administrator corrects a transfer: boxes, destination and date while it is in transit,
     * requester and notes also once received.
     */
    public function correct(User $user, Transfer $transfer): bool
    {
        return $user->isSuperAdmin()
            && in_array($transfer->status, [TransferStatus::InTransit, TransferStatus::Received], true);
    }

    public function receive(User $user, Transfer $transfer): bool
    {
        return $transfer->status === TransferStatus::InTransit && $user->canAccessChamber($transfer->destChamber);
    }

    /**
     * Only a super administrator cancels a transfer, whether it is still in transit or was already received
     * (to correct a mistake). Warehouse users ask the super administrator to do it.
     */
    public function cancel(User $user, Transfer $transfer): bool
    {
        return $user->isSuperAdmin()
            && in_array($transfer->status, [TransferStatus::InTransit, TransferStatus::Received], true);
    }
}
