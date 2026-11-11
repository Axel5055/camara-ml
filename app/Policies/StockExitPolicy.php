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
     * Reversing a confirmed exit: super administrators (granted globally), administrators, and managers within
     * their scope. Warehouse users may only ask for a transfer to be cancelled.
     */
    public function reverse(User $user, StockExit $exit): bool
    {
        return $user->canCorrectRecordsOf($exit->originChamber, $exit->destChamber);
    }

    /**
     * Correcting an exit: same people as reversing it.
     */
    public function update(User $user, StockExit $exit): bool
    {
        return $user->canCorrectRecordsOf($exit->originChamber, $exit->destChamber);
    }

    public function delete(User $user, StockExit $exit): bool
    {
        return false;
    }
}
