<?php

namespace App\Services\Inventory;

use App\Enums\BoxStatus;
use App\Enums\MovementType;
use App\Exceptions\InventoryException;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class EditBox extends InventoryOperation
{
    /**
     * Fix the weight and/or the cost of an available box (super administrators only).
     *
     * An intact box keeps being intact: its initial and current weight both take the new value, as if it had been
     * captured right. A box with partial withdrawals only changes its current weight, which cannot exceed the initial
     * one. The change is appended to the box history and to the audit log with the reason.
     */
    public function handle(
        User $admin,
        Box $box,
        string $reason,
        string|int|float|null $weight = null,
        string|int|float|null $pricePerKg = null,
    ): Box {
        if (! $admin->isSuperAdmin()) {
            throw new AuthorizationException('Solo el super administrador puede editar una caja.');
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw new InventoryException('Indica el motivo del cambio (mínimo 5 caracteres).');
        }

        $weight = $weight === null ? null : Decimal::normalize($weight, Decimal::WEIGHT_SCALE);
        $price = $pricePerKg === null ? null : Decimal::normalize($pricePerKg, Decimal::MONEY_SCALE);

        if ($weight !== null && Decimal::cmp($weight, '0', Decimal::WEIGHT_SCALE) <= 0) {
            throw new InventoryException('El peso debe ser mayor a cero; para dar de baja una caja usa un ajuste.');
        }

        if ($price !== null && Decimal::cmp($price, '0', Decimal::MONEY_SCALE) < 0) {
            throw new InventoryException('El costo por kilo no puede ser negativo.');
        }

        return DB::transaction(function () use ($admin, $box, $reason, $weight, $price): Box {
            $locked = $this->lockBoxes([$box->id])->first() ?? throw new InventoryException('La caja no existe.');

            if ($locked->status !== BoxStatus::Available) {
                throw new InventoryException("La caja {$locked->barcode} no está disponible; solo se editan cajas en existencia.");
            }

            $intact = Decimal::cmp($locked->current_weight_kg, $locked->initial_weight_kg, Decimal::WEIGHT_SCALE) === 0;
            $update = [];
            $notes = [];

            if ($weight !== null && Decimal::cmp($weight, $locked->current_weight_kg, Decimal::WEIGHT_SCALE) !== 0) {
                if (! $intact && Decimal::cmp($weight, $locked->initial_weight_kg, Decimal::WEIGHT_SCALE) > 0) {
                    throw new InventoryException("El peso actual no puede superar el inicial ({$locked->initial_weight_kg} kg).");
                }

                $update['current_weight_kg'] = $weight;

                if ($intact) {
                    $update['initial_weight_kg'] = $weight;
                }

                $notes[] = "peso {$locked->current_weight_kg} → {$weight} kg";
            }

            $priceChanged = $price !== null && Decimal::cmp($price, $locked->price_per_kg_mxn, Decimal::MONEY_SCALE) !== 0;

            if ($priceChanged) {
                $update['price_per_kg_mxn'] = $price;
                $notes[] = "costo por kg {$locked->price_per_kg_mxn} → {$price}";
            }

            // The cost of the box follows its initial weight and its price per kilo.
            if ($priceChanged || isset($update['initial_weight_kg'])) {
                $update['unit_cost_mxn'] = Decimal::costOf(
                    $update['initial_weight_kg'] ?? $locked->initial_weight_kg,
                    $update['price_per_kg_mxn'] ?? $locked->price_per_kg_mxn,
                );
            }

            if ($update === []) {
                throw new InventoryException('No hay cambios que guardar.');
            }

            $now = now();
            $before = $locked->only(['initial_weight_kg', 'current_weight_kg', 'price_per_kg_mxn', 'unit_cost_mxn']);

            $this->insertChunked('box_movements', [$this->movementRow(
                $locked,
                MovementType::Adjustment,
                $admin,
                $now,
                $locked->current_weight_kg,
                $update['current_weight_kg'] ?? $locked->current_weight_kg,
                BoxStatus::Available,
                BoxStatus::Available,
                [
                    'from_chamber_id' => $locked->current_chamber_id,
                    'to_chamber_id' => $locked->current_chamber_id,
                    'notes' => 'Edición de la caja: '.implode(', ', $notes).". {$reason}",
                ],
            )]);

            $locked->update($update);

            AuditLog::record($admin, 'box.edited', $locked, $before, [
                ...$locked->only(['initial_weight_kg', 'current_weight_kg', 'price_per_kg_mxn', 'unit_cost_mxn']),
                'reason' => $reason,
            ]);

            return $locked;
        }, 3);
    }
}
