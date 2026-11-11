<?php

namespace App\Policies;

use App\Models\Chamber;
use App\Models\User;

class ChamberPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Chamber $chamber): bool
    {
        return $user->canViewChamber($chamber);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Chamber $chamber): bool
    {
        return false;
    }

    public function delete(User $user, Chamber $chamber): bool
    {
        return false;
    }
}
