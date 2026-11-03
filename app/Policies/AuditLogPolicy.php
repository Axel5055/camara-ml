<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

/**
 * The audit trail is administrator-only (granted globally by Gate::before).
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, AuditLog $log): bool
    {
        return false;
    }
}
