<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;

class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Incident $incident): bool
    {
        $transfer = $incident->transfer;

        return $user->canAccessChamber($transfer->originChamber) || $user->canAccessChamber($transfer->destChamber);
    }

    /**
     * Only administrators (granted globally) resolve incidents.
     */
    public function resolve(User $user, Incident $incident): bool
    {
        return false;
    }
}
