<?php

namespace App\Policies;

use App\Models\User;

/**
 * Shared rules for catalog models (branches, categories, products): everyone signed in can read them to fill
 * forms; only administrators (granted globally by Gate::before) can change them.
 */
abstract class CatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user): bool
    {
        return false;
    }

    public function delete(User $user): bool
    {
        return false;
    }
}
