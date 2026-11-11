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
        return $user->canViewChamber($entry->chamber);
    }

    /**
     * Registering an entry in a given chamber.
     */
    public function create(User $user, ?Chamber $chamber = null): bool
    {
        return $chamber !== null && $user->canAccessChamber($chamber);
    }

    /**
     * Correcting an entry: super administrators (granted globally) and managers for their own chambers.
     */
    public function update(User $user, Entry $entry): bool
    {
        return $user->canCorrectEntriesOf($entry->chamber);
    }

    /**
     * Cancelling a confirmed entry: super administrators (granted globally) and managers for their own chambers.
     */
    public function reverse(User $user, Entry $entry): bool
    {
        return $user->canCorrectEntriesOf($entry->chamber);
    }

    public function delete(User $user, Entry $entry): bool
    {
        return false;
    }
}
