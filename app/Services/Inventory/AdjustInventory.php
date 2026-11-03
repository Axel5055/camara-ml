<?php

namespace App\Services\Inventory;

use App\Enums\AdjustmentType;
use App\Enums\BoxStatus;
use App\Enums\MovementType;
use App\Exceptions\InventoryException;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\Chamber;
use App\Models\ExitItem;
use App\Models\InventoryAdjustment;
use App\Models\User;
use App\Services\Barcodes\SequenceAllocator;
use App\Support\Decimal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class AdjustInventory extends InventoryOperation
{
    public function __construct(private SequenceAllocator $sequences) {}

    /**
     * Authorized correction of a single box. Only administrators may apply it; a reason is mandatory and the
     * before/after state is stored in the adjustment, the movement ledger and the audit log.
     */
    public function handle(
        User $admin,
        Box $box,
        AdjustmentType $type,
        string $reason,
        string|int|float|null $newWeight = null,
        ?BoxStatus $newStatus = null,
        ?Chamber $chamber = null,
        ?User $requestedBy = null,
    ): InventoryAdjustment {
        if (! $admin->isSuperAdmin()) {
            throw new AuthorizationException('Solo un administrador puede autorizar ajustes de inventario.');
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw new InventoryException('Indica el motivo del ajuste (mínimo 5 caracteres).');
        }

        $newWeight = $newWeight === null ? null : Decimal::normalize($newWeight, Decimal::WEIGHT_SCALE);

        if ($chamber !== null && ! $chamber->is_active) {
            throw new InventoryException("La cámara {$chamber->name} está inactiva.");
        }

        $reference = $this->sequences->reference('AJU', $this->sequences->next('adjustment'));

        return DB::transaction(function () use ($admin, $box, $type, $reason, $newWeight, $newStatus, $chamber, $requestedBy, $reference): InventoryAdjustment {
            $locked = $this->lockBoxes([$box->id])->first() ?? throw new InventoryException('La caja no existe.');

            [$weight, $status, $chamberId] = $this->target($locked, $type, $newWeight, $newStatus, $chamber);

            $before = ['weight_kg' => $locked->current_weight_kg, 'status' => $locked->status->value, 'chamber_id' => $locked->current_chamber_id];
            $now = now();

            $adjustment = InventoryAdjustment::create([
                'reference' => $reference,
                'chamber_id' => $chamberId ?? $locked->current_chamber_id,
                'box_id' => $locked->id,
                'type' => $type,
                'weight_before_kg' => $locked->current_weight_kg,
                'weight_after_kg' => $weight,
                'status_before' => $locked->status->value,
                'status_after' => $status->value,
                'reason' => $reason,
                'requested_by' => ($requestedBy ?? $admin)->id,
                'authorized_by' => $admin->id,
                'occurred_at' => $now,
            ]);

            $this->insertChunked('box_movements', [$this->movementRow(
                $locked,
                MovementType::Adjustment,
                $admin,
                $now,
                $locked->current_weight_kg,
                $weight,
                $locked->status,
                $status,
                [
                    'from_chamber_id' => $locked->current_chamber_id,
                    'to_chamber_id' => $chamberId,
                    'adjustment_id' => $adjustment->id,
                    'notes' => $reason,
                ],
            )]);

            $locked->update(['current_weight_kg' => $weight, 'status' => $status, 'current_chamber_id' => $chamberId]);

            if ($status === BoxStatus::Available) {
                // A box found or put back in stock is no longer held out by the exit that took it.
                ExitItem::query()->where('box_id', $locked->id)->where('holds_box', true)->update(['holds_box' => false, 'updated_at' => $now]);
            }

            AuditLog::record($admin, 'inventory.adjusted', $locked, $before, [
                'weight_kg' => $weight, 'status' => $status->value, 'chamber_id' => $chamberId, 'reference' => $reference,
            ]);

            return $adjustment;
        }, 3);
    }

    /**
     * @return array{0: string, 1: BoxStatus, 2: int|null} weight, status, chamber id after the adjustment
     */
    private function target(Box $box, AdjustmentType $type, ?string $newWeight, ?BoxStatus $newStatus, ?Chamber $chamber): array
    {
        $weight = $box->current_weight_kg;

        switch ($type) {
            case AdjustmentType::WeightCorrection:
                if ($newWeight === null || $box->status !== BoxStatus::Available) {
                    throw new InventoryException('La corrección de peso requiere el nuevo peso y una caja disponible.');
                }
                $weight = $newWeight;
                $status = Decimal::cmp($weight, '0', Decimal::WEIGHT_SCALE) === 0 ? BoxStatus::Depleted : BoxStatus::Available;
                $chamberId = $status === BoxStatus::Available ? $box->current_chamber_id : null;
                break;

            case AdjustmentType::Lost:
            case AdjustmentType::Damaged:
                if (! in_array($box->status, [BoxStatus::Available, BoxStatus::InTransit], true)) {
                    throw new InventoryException('Solo se puede dar de baja una caja disponible o en tránsito.');
                }
                $status = BoxStatus::WrittenOff;
                $chamberId = null;
                break;

            case AdjustmentType::Found:
                if ($chamber === null || ! in_array($box->status, [BoxStatus::InTransit, BoxStatus::Dispatched, BoxStatus::WrittenOff], true)) {
                    throw new InventoryException('Para registrar una caja encontrada indica la cámara; la caja debe estar fuera del inventario.');
                }
                $weight = $newWeight ?? $weight;
                $status = BoxStatus::Available;
                $chamberId = $chamber->id;
                break;

            case AdjustmentType::StatusFix:
                $status = $newStatus ?? throw new InventoryException('Indica el nuevo estado de la caja.');
                $weight = $newWeight ?? $weight;
                $chamberId = $status === BoxStatus::Available
                    ? ($chamber === null ? throw new InventoryException('Indica la cámara de la caja disponible.') : $chamber->id)
                    : null;
                break;
        }

        if (Decimal::cmp($weight, '0', Decimal::WEIGHT_SCALE) < 0 || Decimal::cmp($weight, $box->initial_weight_kg, Decimal::WEIGHT_SCALE) > 0) {
            throw new InventoryException("El peso debe estar entre 0 y el peso inicial ({$box->initial_weight_kg} kg).");
        }

        if ($status === BoxStatus::Depleted && Decimal::cmp($weight, '0', Decimal::WEIGHT_SCALE) !== 0) {
            throw new InventoryException('Una caja agotada debe pesar 0 kg.');
        }

        if ($status === BoxStatus::Available && Decimal::cmp($weight, '0', Decimal::WEIGHT_SCALE) === 0) {
            throw new InventoryException('Una caja disponible debe tener peso mayor a cero.');
        }

        return [$weight, $status, $chamberId];
    }
}
