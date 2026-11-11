<?php

namespace App\Policies;

use App\Enums\UserRole;
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

        return $user->canViewChamber($transfer->originChamber) || $user->canViewChamber($transfer->destChamber);
    }

    /**
     * Resolving an incident: super administrators (granted globally), administrators, and managers for the
     * incidents of their own chambers.
     */
    public function resolve(User $user, Incident $incident): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Manager => $this->view($user, $incident),
            default => false,
        };
    }
}
