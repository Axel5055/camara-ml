<?php

namespace App\Policies;

use App\Models\Box;
use App\Models\User;

class BoxPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * A warehouse user sees a box while it is in, or came from, one of their chambers.
     */
    public function view(User $user, Box $box): bool
    {
        $box->loadMissing('currentChamber', 'originChamber');

        return ($box->currentChamber !== null && $user->canAccessChamber($box->currentChamber))
            || $user->canAccessChamber($box->originChamber);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Box $box): bool
    {
        return false;
    }

    public function delete(User $user, Box $box): bool
    {
        return false;
    }
}
