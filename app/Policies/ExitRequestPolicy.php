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
        return $user->role !== UserRole::Manager;
    }

    /**
     * Administrators see all of them, requesters only their own, warehouse users their own plus those of the
     * chambers within their scope.
     */
    public function view(User $user, ExitRequest $request): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role === UserRole::Requester) {
            return $user->id === $request->user_id;
        }

        if ($user->role === UserRole::Manager) {
            return false;
        }

        return ($user->is_active && $user->id === $request->user_id) || $user->canViewChamber($request->chamber);
    }

    /**
     * Asking a chamber for product: requesters and warehouse users, for the chambers assigned to them for that, and
     * super administrators for any chamber on behalf of a branch.
     */
    public function create(User $user, ?Chamber $chamber = null): bool
    {
        if ($user->isSuperAdmin()) {
            return $chamber !== null;
        }

        return $user->asksForProduct()
            && $user->branch_id !== null
            && $chamber !== null
            && $user->canRequestFrom($chamber);
    }

    /**
     * Authorizing or rejecting: the warehouse users of the chamber, who can never resolve a request they made
     * themselves, and super administrators, who can resolve any pending one, their own included. Administrators
     * and managers only look.
     */
    public function decide(User $user, ExitRequest $request): bool
    {
        if (! $request->isPending() || ($request->user_id === $user->id && ! $user->isSuperAdmin())) {
            return false;
        }

        return $user->canAccessChamber($request->chamber);
    }

    /**
     * Validating an authorized request, which moves its exit to the direct ones: the warehouse user of the chamber
     * and super administrators, once and only while the exit still stands.
     */
    public function validate(User $user, ExitRequest $request): bool
    {
        return $request->awaitsValidation() && $user->canAccessChamber($request->chamber);
    }

    /**
     * Whoever made a request (a requester, or a super administrator on behalf of a branch) can withdraw it while
     * it is still pending.
     */
    public function cancel(User $user, ExitRequest $request): bool
    {
        return $request->isPending() && $request->user_id === $user->id;
    }
}
