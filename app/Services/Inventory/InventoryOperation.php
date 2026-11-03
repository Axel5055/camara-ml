<?php

namespace App\Services\Inventory;

use App\Enums\BoxStatus;
use App\Enums\MovementType;
use App\Exceptions\InventoryException;
use App\Models\Box;
use App\Models\Chamber;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared plumbing for the services that are allowed to change inventory.
 */
abstract class InventoryOperation
{
    protected function authorizeChamber(User $user, Chamber $chamber): void
    {
        if (! $user->canAccessChamber($chamber)) {
            throw new AuthorizationException('No tienes acceso a la cámara seleccionada.');
        }

        if (! $chamber->is_active) {
            throw new InventoryException("La cámara {$chamber->name} está inactiva.");
        }
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    protected function uniqueIds(array $ids): array
    {
        $ids = array_map('intval', $ids);

        if ($ids === []) {
            throw new InventoryException('Selecciona al menos una caja.');
        }

        if (count($ids) !== count(array_unique($ids))) {
            throw new InventoryException('Hay cajas repetidas en la selección.');
        }

        return array_values($ids);
    }

    /**
     * Lock boxes in a deterministic order (prevents deadlocks between concurrent operators).
     *
     * @param  array<mixed>  $ids
     * @return Collection<int, Box>
     */
    protected function lockBoxes(array $ids): Collection
    {
        return Box::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function movementRow(
        Box $box,
        MovementType $type,
        User $user,
        CarbonInterface $at,
        string $weightBefore,
        string $weightAfter,
        ?BoxStatus $statusBefore,
        BoxStatus $statusAfter,
        array $extra = [],
    ): array {
        return array_merge([
            'box_id' => $box->id,
            'type' => $type->value,
            'from_chamber_id' => null,
            'to_chamber_id' => null,
            'to_branch_id' => null,
            'weight_before_kg' => $weightBefore,
            'weight_after_kg' => $weightAfter,
            'status_before' => $statusBefore?->value,
            'status_after' => $statusAfter->value,
            'user_id' => $user->id,
            'occurred_at' => $at,
            'entry_id' => null,
            'exit_id' => null,
            'transfer_id' => null,
            'adjustment_id' => null,
            'reverses_movement_id' => null,
            'notes' => null,
            'created_at' => now(),
        ], $extra);
    }

    /**
     * @param  array<array<string, mixed>>  $rows
     */
    protected function insertChunked(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
