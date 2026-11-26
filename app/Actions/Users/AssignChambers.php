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
     * Replace the chambers a warehouse user may work in (they must belong to the user's own branch) or a requester
     * may ask for product (they are in other branches, since the requester's branch has no chambers). Warehouse
     * users also get, apart, the chambers they may ask for product: any they do not operate.
     *
     * @param  array<int, int>  $chamberIds
     * @param  array<int, int>|null  $requestableChamberIds  chambers a warehouse user may ask; null keeps them
     */
    public function handle(User $actor, User $user, array $chamberIds, ?array $requestableChamberIds = null): void
    {
        if (! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Solo un administrador puede asignar cámaras.');
        }

        if (! in_array($user->role, [UserRole::Warehouse, UserRole::Requester], true)) {
            throw new InventoryException('Solo los almacenistas y los solicitantes tienen cámaras asignadas.');
        }

        if ($user->branch_id === null) {
            throw new InventoryException('Asigna primero una sucursal al usuario.');
        }

        $chambers = Chamber::query()->whereKey($chamberIds)->get();

        if ($chambers->count() !== count(array_unique($chamberIds))) {
            throw new InventoryException('Alguna de las cámaras seleccionadas no existe.');
        }

        if ($user->role === UserRole::Warehouse && $chambers->contains(fn (Chamber $chamber): bool => $chamber->branch_id !== $user->branch_id)) {
            throw new InventoryException('Las cámaras deben pertenecer a la sucursal del almacenista.');
        }

        if ($user->role !== UserRole::Warehouse) {
            $requestableChamberIds = [];
        } elseif ($requestableChamberIds !== null) {
            if (Chamber::query()->whereKey($requestableChamberIds)->count() !== count(array_unique($requestableChamberIds))) {
                throw new InventoryException('Alguna de las cámaras seleccionadas no existe.');
            }

            if (array_intersect($requestableChamberIds, $chambers->modelKeys()) !== []) {
                throw new InventoryException('Un almacenista no puede pedir producto a una cámara que ya opera.');
            }
        }

        $user->chambers()->sync($chambers->modelKeys());

        if ($requestableChamberIds !== null) {
            $user->requestableChambers()->sync($requestableChamberIds);
        }
    }
}
