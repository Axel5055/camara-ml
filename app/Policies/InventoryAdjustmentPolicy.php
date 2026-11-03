<?php

namespace App\Policies;

use App\Models\InventoryAdjustment;
use App\Models\User;

/**
 * Adjustments are administrator-only (granted globally by Gate::before).
 */
class InventoryAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, InventoryAdjustment $adjustment): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }
}
