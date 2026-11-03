<?php

namespace App\Services\Inventory;

use App\Enums\BoxStatus;
use App\Enums\DocumentStatus;
use App\Enums\ExitItemKind;
use App\Enums\IncidentStatus;
use App\Enums\MovementType;
use App\Enums\TransferItemStatus;
use App\Enums\TransferStatus;
use App\Exceptions\InventoryException;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\BoxMovement;
use App\Models\ExitItem;
use App\Models\Incident;
use App\Models\StockExit;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ReverseExit extends InventoryOperation
{
    /**
     * Reverse a confirmed exit, restoring boxes (and withdrawn weight) to the origin chamber.
     *
     * - Plain deliveries and partial withdrawals: administrators only.
     * - Exits with a transfer (the "cancel transfer" flow): super administrators only. If the transfer was
     *   already received, only while every received box is still available and untouched in the destination
     *   chamber; its open incidents are closed.
     *
     * Nothing is deleted: the exit is marked reversed, its items inactive, and inverse movements are appended.
     */
    public function handle(User $user, StockExit $exit, string $reason): StockExit
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw new InventoryException('Indica el motivo de la reversión (mínimo 5 caracteres).');
        }

        $exit->loadMissing('originChamber', 'transfer');

        if (! $user->isSuperAdmin()) {
            throw new AuthorizationException($exit->transfer === null
                ? 'Solo un administrador puede revertir una salida.'
                : 'Solo el super administrador puede cancelar una transferencia.');
        }

        return DB::transaction(function () use ($user, $exit, $reason): StockExit {
            $locked = StockExit::query()->whereKey($exit->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== DocumentStatus::Confirmed) {
                throw new InventoryException("La salida {$locked->reference} ya fue revertida.");
            }

            $transfer = $locked->transfer()->lockForUpdate()->first();

            $wasReceived = $transfer?->status === TransferStatus::Received;

            if ($transfer !== null && $transfer->status !== TransferStatus::InTransit && ! $wasReceived) {
                throw new InventoryException("La transferencia {$transfer->reference} ya no está en tránsito y no puede revertirse.");
            }

            $items = ExitItem::query()->where('exit_id', $locked->id)->where('is_active', true)->get();
            $boxes = $this->lockBoxes($items->pluck('box_id')->all());
            $originalMovements = BoxMovement::query()
                ->where('exit_id', $locked->id)
                ->whereIn('type', [MovementType::Exit->value, MovementType::TransferOut->value, MovementType::PartialWithdrawal->value])
                ->pluck('id', 'box_id');

            $now = now();
            $movements = [];

            // Boxes that physically arrived (the missing ones are still in transit).
            $arrivedBoxIds = $wasReceived
                ? TransferItem::query()->where('transfer_id', $transfer->id)->where('status', TransferItemStatus::Received->value)->pluck('box_id')->all()
                : [];

            foreach ($items as $item) {
                $box = $boxes[$item->box_id];
                $restoredWeight = $this->restoredWeight($item, $box, $wasReceived ? $transfer : null, in_array($box->id, $arrivedBoxIds, true));

                $movements[] = $this->movementRow(
                    $box,
                    $transfer ? MovementType::TransferCancel : MovementType::Reversal,
                    $user,
                    $now,
                    $box->current_weight_kg,
                    $restoredWeight,
                    $box->status,
                    BoxStatus::Available,
                    [
                        'from_chamber_id' => $box->current_chamber_id,
                        'to_chamber_id' => $locked->origin_chamber_id,
                        'exit_id' => $locked->id,
                        'transfer_id' => $transfer?->id,
                        'reverses_movement_id' => $originalMovements[$box->id] ?? null,
                        'notes' => $reason,
                    ],
                );

                $box->update([
                    'status' => BoxStatus::Available,
                    'current_chamber_id' => $locked->origin_chamber_id,
                    'current_weight_kg' => $restoredWeight,
                ]);
            }

            ExitItem::query()->where('exit_id', $locked->id)->update(['is_active' => false, 'updated_at' => $now]);

            if ($wasReceived) {
                Incident::query()->where('transfer_id', $transfer->id)->where('status', IncidentStatus::Open->value)->update([
                    'status' => IncidentStatus::Resolved->value,
                    'resolved_by' => $user->id,
                    'resolved_at' => $now,
                    'resolution' => 'Transferencia cancelada por el super administrador: '.$reason,
                    'updated_at' => $now,
                ]);
            }
            $this->insertChunked('box_movements', $movements);

            $locked->update([
                'status' => DocumentStatus::Reversed,
                'reversed_at' => $now,
                'reversed_by' => $user->id,
                'reversed_reason' => $reason,
            ]);

            $transfer?->update([
                'status' => TransferStatus::Cancelled,
                'cancelled_at' => $now,
                'cancelled_by' => $user->id,
                'cancel_reason' => $reason,
            ]);

            AuditLog::record($user, $transfer ? 'transfer.cancelled' : 'exit.reversed', $locked, ['status' => 'confirmed', 'transfer_status' => $transfer?->status->value], ['status' => 'reversed', 'reason' => $reason]);

            return $locked->refresh();
        }, 3);
    }

    /**
     * Validate that the box is still in the state the exit left it in, and return the weight it must get back.
     */
    private function restoredWeight(ExitItem $item, Box $box, ?Transfer $receivedTransfer = null, bool $arrived = false): string
    {
        if ($item->kind === ExitItemKind::Full) {
            if ($receivedTransfer !== null && $arrived) {
                $untouched = $box->status === BoxStatus::Available
                    && $box->current_chamber_id === $receivedTransfer->dest_chamber_id
                    && Decimal::cmp($box->current_weight_kg, $item->weight_before_kg, Decimal::WEIGHT_SCALE) === 0;

                if (! $untouched) {
                    throw new InventoryException("La caja {$box->barcode} ya no está intacta en la cámara destino; no se puede cancelar la transferencia.");
                }

                return $box->current_weight_kg;
            }

            if (! in_array($box->status, [BoxStatus::Dispatched, BoxStatus::InTransit], true)) {
                throw new InventoryException("La caja {$box->barcode} ya no está fuera del inventario; no se puede revertir la salida.");
            }

            return $box->current_weight_kg;
        }

        $untouched = Decimal::cmp($box->current_weight_kg, $item->weight_after_kg, Decimal::WEIGHT_SCALE) === 0
            && ($box->status === BoxStatus::Depleted || $box->status === BoxStatus::Available);

        if (! $untouched) {
            throw new InventoryException("La caja {$box->barcode} tuvo movimientos posteriores al retiro parcial; use un ajuste autorizado.");
        }

        return Decimal::add($box->current_weight_kg, $item->weight_out_kg, Decimal::WEIGHT_SCALE);
    }
}
