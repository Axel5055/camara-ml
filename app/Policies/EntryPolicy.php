<?php

namespace App\Policies;

use App\Models\Chamber;
use App\Models\Entry;
use App\Models\User;

class EntryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Entry $entry): bool
    {
        return $user->canAccessChamber($entry->chamber);
    }

    /**
     * Registering an entry in a given chamber.
     */
    public function create(User $user, ?Chamber $chamber = null): bool
    {
        return $chamber !== null && $user->canAccessChamber($chamber);
    }

    public function update(User $user, Entry $entry): bool
    {
        return false;
    }

    public function delete(User $user, Entry $entry): bool
    {
        return false;
    }
}
