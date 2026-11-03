<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Exceptions\InventoryException;
use App\Models\Chamber;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class AssignChambers
{
    /**
     * Replace the chambers a warehouse user may work in. They must belong to the user's own branch.
     *
     * @param  array<int, int>  $chamberIds
     */
    public function handle(User $actor, User $user, array $chamberIds): void
    {
        if (! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Solo un administrador puede asignar cámaras.');
        }

        if ($user->role !== UserRole::Warehouse) {
            throw new InventoryException('Solo los almacenistas tienen cámaras asignadas.');
        }

        if ($user->branch_id === null) {
            throw new InventoryException('Asigna primero una sucursal al almacenista.');
        }

        $chambers = Chamber::query()->whereKey($chamberIds)->get();

        if ($chambers->count() !== count(array_unique($chamberIds))) {
            throw new InventoryException('Alguna de las cámaras seleccionadas no existe.');
        }

        if ($chambers->contains(fn (Chamber $chamber): bool => $chamber->branch_id !== $user->branch_id)) {
            throw new InventoryException('Las cámaras deben pertenecer a la sucursal del almacenista.');
        }

        $user->chambers()->sync($chambers->modelKeys());
    }
}
