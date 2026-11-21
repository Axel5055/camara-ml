<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Chamber;
use App\Models\ExitRequest;
use App\Models\User;

class ExitRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Administrators see all of them, requesters those of their own branch, everyone else those of the chambers
     * within their scope.
     */
    public function view(User $user, ExitRequest $request): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role === UserRole::Requester) {
            return $user->branch_id === $request->branch_id;
        }

        return $user->canViewChamber($request->chamber);
    }

    /**
     * Asking a chamber for product: requesters, for the chambers assigned to them.
     */
    public function create(User $user, ?Chamber $chamber = null): bool
    {
        return $user->role === UserRole::Requester
            && $user->branch_id !== null
            && $chamber !== null
            && $user->canRequestFrom($chamber);
    }

    /**
     * Authorizing or rejecting: the warehouse users of the chamber and super administrators, never the one who
     * asked. Administrators and managers only look.
     */
    public function decide(User $user, ExitRequest $request): bool
    {
        if (! $request->isPending() || $request->user_id === $user->id) {
            return false;
        }

        return $user->canAccessChamber($request->chamber);
    }

    /**
     * Requesters can withdraw their own request while it is still pending.
     */
    public function cancel(User $user, ExitRequest $request): bool
    {
        return $request->isPending() && $request->user_id === $user->id && $user->role === UserRole::Requester;
    }
}
