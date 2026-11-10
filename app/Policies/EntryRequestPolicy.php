<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Chamber;
use App\Models\EntryRequest;
use App\Models\User;

class EntryRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, EntryRequest $request): bool
    {
        return $user->role === UserRole::Admin
            || $request->user_id === $user->id
            || $user->canAccessChamber($request->chamber);
    }

    /**
     * Asking for an entry in a chamber: warehouse users, for the chambers assigned to them.
     */
    public function create(User $user, ?Chamber $chamber = null): bool
    {
        return $user->role === UserRole::Warehouse && $chamber !== null && $user->canAccessChamber($chamber);
    }

    /**
     * Authorizing or rejecting: administrators and super administrators, never the one who asked.
     */
    public function decide(User $user, EntryRequest $request): bool
    {
        return $request->isPending()
            && $request->user_id !== $user->id
            && in_array($user->role, [UserRole::Admin, UserRole::SuperAdmin], true);
    }

    /**
     * The requester can withdraw their own request while it is still pending.
     */
    public function cancel(User $user, EntryRequest $request): bool
    {
        return $request->isPending() && $request->user_id === $user->id;
    }
}
