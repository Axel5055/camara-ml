<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Warehouse users, administrators and managers may register new products, which belong to the whole catalog
 * (every chamber); they cannot edit, deactivate or delete them. Super administrators do everything.
 */
class ProductPolicy extends CatalogPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Warehouse, UserRole::Admin, UserRole::Manager], true);
    }
}
