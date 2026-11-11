<?php

namespace App\Policies;

use App\Models\Chamber;
use App\Models\StockExit;
use App\Models\User;

class StockExitPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, StockExit $exit): bool
    {
        return $user->canViewChamber($exit->originChamber)
            || ($exit->destChamber !== null && $user->canViewChamber($exit->destChamber));
    }

    /**
     * Registering an exit from a given chamber.
     */
    public function create(User $user, ?Chamber $origin = null): bool
    {
        return $origin !== null && $user->canAccessChamber($origin);
    }

    /**
     * Reversing a confirmed exit. Administrators are granted globally; warehouse users may only cancel
     * a transfer through TransferPolicy::cancel.
     */
    public function reverse(User $user, StockExit $exit): bool
    {
        return false;
    }

    public function update(User $user, StockExit $exit): bool
    {
        return false;
    }

    public function delete(User $user, StockExit $exit): bool
    {
        return false;
    }
}
