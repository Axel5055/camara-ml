<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Warehouse users may register new products (they cannot edit, deactivate or delete them); super administrators
 * do everything, and the read-only administrator only looks.
 */
class ProductPolicy extends CatalogPolicy
{
    public function create(User $user): bool
    {
        return $user->role === UserRole::Warehouse;
    }
}
