<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Exceptions\InventoryException;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class AssignManagerScope
{
    /**
     * Replace the branches (with all their chambers) and the individual chambers a manager may see.
     *
     * @param  array<int, int>  $branchIds
     * @param  array<int, int>  $chamberIds
     */
    public function handle(User $actor, User $manager, array $branchIds, array $chamberIds): void
    {
        if (! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Solo un super administrador puede asignar sucursales y cámaras.');
        }

        if ($manager->role !== UserRole::Manager) {
            throw new InventoryException('Solo los gerentes tienen sucursales y cámaras asignadas.');
        }

        $branchIds = array_values(array_unique($branchIds));
        $chamberIds = array_values(array_unique($chamberIds));

        if (Branch::query()->whereKey($branchIds)->count() !== count($branchIds)) {
            throw new InventoryException('Alguna de las sucursales seleccionadas no existe.');
        }

        if (Chamber::query()->whereKey($chamberIds)->count() !== count($chamberIds)) {
            throw new InventoryException('Alguna de las cámaras seleccionadas no existe.');
        }

        if ($branchIds === [] && $chamberIds === []) {
            throw new InventoryException('Asigna al menos una sucursal o una cámara al gerente.');
        }

        $manager->managedBranches()->sync($branchIds);
        $manager->chambers()->sync($chamberIds);
    }
}
