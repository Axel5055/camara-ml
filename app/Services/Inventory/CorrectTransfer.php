<?php

namespace App\Services\Inventory;

use App\Enums\BoxStatus;
use App\Enums\DocumentStatus;
use App\Enums\ExitItemKind;
use App\Enums\MovementType;
use App\Enums\TransferItemStatus;
use App\Enums\TransferStatus;
use App\Exceptions\InventoryException;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\BoxMovement;
use App\Models\Chamber;
use App\Models\ExitItem;
use App\Models\StockExit;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\User;
use App\Support\Decimal;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CorrectTransfer extends InventoryOperation
{
    /**
     * Correct a transfer registered by mistake (super administrators only).
     *
     * Requester and notes can change while the transfer is in transit or received. Destination chamber, dispatch
     * date and boxes only while it is in transit: removed boxes go back to the origin chamber and added ones must be
     * available there. Every change is appended to the box histories and the audit log.
     *
     * @param  array<int, int|string>  $removeBoxIds
     * @param  array<int, int|string>  $addBoxIds
     */
    public function handle(
        User $admin,
        Transfer $transfer,
        string $reason,
        string $requesterName,
        ?string $notes = null,
        ?CarbonInterface $dispatchedAt = null,
        ?Chamber $destChamber = null,
        array $removeBoxIds = [],
        array $addBoxIds = [],
    ): Transfer {
        if (! $admin->canCorrectRecordsOf($transfer->originChamber, $transfer->destChamber, $destChamber)) {
            throw new AuthorizationException('No tienes permiso para corregir esta transferencia.');
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw new InventoryException('Indica el motivo de la corrección (mínimo 5 caracteres).');
        }

        $requesterName = trim($requesterName);

        if ($requesterName === '') {
            throw new InventoryException('Indica quién solicita la mercancía.');
        }

        $removeBoxIds = array_values(array_unique(array_map('intval', $removeBoxIds)));
        $addBoxIds = array_values(array_unique(array_map('intval', $addBoxIds)));

        if (array_intersect($removeBoxIds, $addBoxIds) !== []) {
            throw new InventoryException('Una misma caja no puede quitarse y agregarse a la vez.');
        }

        return DB::transaction(function () use ($admin, $transfer, $reason, $requesterName, $notes, $dispatchedAt, $destChamber, $removeBoxIds, $addBoxIds): Transfer {
            $locked = Transfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $exit = StockExit::query()->whereKey($locked->exit_id)->lockForUpdate()->firstOrFail();

            if ($exit->status !== DocumentStatus::Confirmed || ! in_array($locked->status, [TransferStatus::InTransit, TransferStatus::Received], true)) {
                throw new InventoryException("La transferencia {$locked->reference} está cancelada y no puede corregirse.");
            }

            $inTransit = $locked->status === TransferStatus::InTransit;
            $newDest = $destChamber ?? $locked->destChamber()->firstOrFail();
            $newDate = $dispatchedAt ?? $locked->dispatched_at;
            $destChanged = $newDest->id !== $locked->dest_chamber_id;
            $dateChanged = ! $newDate->equalTo($locked->dispatched_at);

            if (! $inTransit && ($destChanged || $dateChanged || $removeBoxIds !== [] || $addBoxIds !== [])) {
                throw new InventoryException('Una transferencia ya recibida solo permite corregir el solicitante y las observaciones; cancélala y registra otra para cambiar lo demás.');
            }

            if ($destChanged) {
                if (! $newDest->is_active) {
                    throw new InventoryException("La cámara {$newDest->name} está inactiva.");
                }

                if ($newDest->id === $locked->origin_chamber_id) {
                    throw new InventoryException('La cámara destino debe ser distinta a la de origen.');
                }
            }

            $newNotes = $notes === null ? $exit->notes : (trim($notes) === '' ? null : trim($notes));
            $now = now();

            $exitItems = ExitItem::query()->where('exit_id', $exit->id)->orderBy('id')->get()->keyBy('box_id');
            $removed = $removeBoxIds === [] ? [] : $this->removeBoxes($admin, $locked, $exit, $removeBoxIds, $reason, $now);
            $added = $addBoxIds === [] ? [] : $this->addBoxes($admin, $locked, $exit, $newDest, $exitItems, $addBoxIds, $reason, $now);

            $headerChanged = $requesterName !== $exit->requester_name || $newNotes !== $exit->notes || $dateChanged || $destChanged;

            if (! $headerChanged && $removed === [] && $added === []) {
                throw new InventoryException('No hay cambios que guardar.');
            }

            $active = ExitItem::query()->where('exit_id', $exit->id)->where('is_active', true)->get();

            if ($active->isEmpty()) {
                throw new InventoryException('La transferencia debe conservar al menos una caja; para anularla usa la cancelación.');
            }

            $fields = ['requester_name', 'occurred_at', 'notes', 'dest_chamber_id', 'dest_branch_id', 'total_boxes', 'total_weight_kg', 'total_cost_mxn'];
            $old = $exit->only($fields) + ['dispatched_at' => $locked->dispatched_at];

            $exit->update([
                'requester_name' => $requesterName,
                'occurred_at' => $newDate,
                'notes' => $newNotes,
                'dest_chamber_id' => $newDest->id,
                'dest_branch_id' => $destChanged ? $newDest->branch_id : $exit->dest_branch_id,
                'total_boxes' => $active->count(),
                'total_weight_kg' => $active->reduce(fn (string $sum, ExitItem $item): string => Decimal::add($sum, $item->weight_out_kg, Decimal::WEIGHT_SCALE), '0.000'),
                'total_cost_mxn' => $active->reduce(fn (string $sum, ExitItem $item): string => Decimal::add($sum, $item->cost_out_mxn, Decimal::MONEY_SCALE), '0.00'),
            ]);

            $locked->update(['dest_chamber_id' => $newDest->id, 'dispatched_at' => $newDate]);

            AuditLog::record($admin, 'transfer.corrected', $locked, $old, [
                ...$exit->only($fields),
                'dispatched_at' => $newDate,
                'reason' => $reason,
                'removed_boxes' => $removed,
                'added_boxes' => $added,
            ]);

            return $locked->refresh();
        }, 3);
    }

    /**
     * @param  list<int>  $boxIds
     * @return list<string> barcodes of the boxes that went back to the origin chamber
     */
    private function removeBoxes(User $admin, Transfer $transfer, StockExit $exit, array $boxIds, string $reason, CarbonInterface $now): array
    {
        $present = TransferItem::query()->where('transfer_id', $transfer->id)->pluck('box_id')->all();
        $foreign = array_diff($boxIds, $present);

        if ($foreign !== []) {
            throw new InventoryException('Cajas que no pertenecen a la transferencia: '.implode(', ', $foreign).'.');
        }

        $boxes = $this->lockBoxes($boxIds);
        $originals = BoxMovement::query()->where('transfer_id', $transfer->id)->where('type', MovementType::TransferOut->value)->pluck('id', 'box_id');
        $movements = [];

        foreach ($boxes as $box) {
            if ($box->status !== BoxStatus::InTransit) {
                throw new InventoryException("La caja {$box->barcode} ya no está en tránsito; no se puede quitar de la transferencia.");
            }

            $movements[] = $this->movementRow($box, MovementType::Reversal, $admin, $now, $box->current_weight_kg, $box->current_weight_kg, BoxStatus::InTransit, BoxStatus::Available, [
                'to_chamber_id' => $exit->origin_chamber_id,
                'exit_id' => $exit->id,
                'transfer_id' => $transfer->id,
                'reverses_movement_id' => $originals[$box->id] ?? null,
                'notes' => "Caja quitada al corregir la transferencia {$transfer->reference}. {$reason}",
            ]);
        }

        Box::query()->whereIn('id', $boxIds)->update(['status' => BoxStatus::Available->value, 'current_chamber_id' => $exit->origin_chamber_id, 'updated_at' => $now]);
        ExitItem::query()->where('exit_id', $exit->id)->whereIn('box_id', $boxIds)->update(['is_active' => false, 'updated_at' => $now]);
        TransferItem::query()->where('transfer_id', $transfer->id)->whereIn('box_id', $boxIds)->update(['removed_at' => $now, 'updated_at' => $now]);
        $this->insertChunked('box_movements', $movements);

        return array_values(array_map(fn (Box $box): string => $box->barcode, $boxes->all()));
    }

    /**
     * @param  Collection<int, ExitItem>  $exitItems  every item of the exit, keyed by box id
     * @param  list<int>  $boxIds
     * @return list<string> barcodes of the boxes added to the transfer
     */
    private function addBoxes(User $admin, Transfer $transfer, StockExit $exit, Chamber $dest, $exitItems, array $boxIds, string $reason, CarbonInterface $now): array
    {
        $boxes = $this->lockBoxes($boxIds);
        $missing = array_diff($boxIds, $boxes->keys()->all());

        if ($missing !== []) {
            throw new InventoryException('Cajas inexistentes: '.implode(', ', $missing).'.');
        }

        $unavailable = $boxes->filter(fn (Box $box): bool => $box->status !== BoxStatus::Available || $box->current_chamber_id !== $exit->origin_chamber_id);

        if ($unavailable->isNotEmpty()) {
            throw new InventoryException('Cajas no disponibles en la cámara de origen: '.$unavailable->pluck('barcode')->implode(', ').'.');
        }

        $movements = [];
        $newExitItems = [];
        $newTransferItems = [];

        foreach ($boxes as $box) {
            $previous = $exitItems->get($box->id);
            $previousTransferItem = TransferItem::withoutGlobalScopes()->where('transfer_id', $transfer->id)->where('box_id', $box->id)->first();

            if ($previous !== null) {
                if (Decimal::cmp($previous->weight_before_kg, $box->current_weight_kg, Decimal::WEIGHT_SCALE) !== 0) {
                    throw new InventoryException("La caja {$box->barcode} cambió de peso desde que salió; regístrala en una transferencia nueva.");
                }

                ExitItem::query()->whereKey($previous->id)->update(['is_active' => true, 'holds_box' => true, 'updated_at' => $now]);
            } else {
                $newExitItems[] = [
                    'exit_id' => $exit->id,
                    'box_id' => $box->id,
                    'kind' => ExitItemKind::Full->value,
                    'weight_before_kg' => $box->current_weight_kg,
                    'weight_out_kg' => $box->current_weight_kg,
                    'weight_after_kg' => '0.000',
                    'unit_cost_snapshot_mxn' => $box->unit_cost_mxn,
                    'cost_out_mxn' => $box->unit_cost_mxn,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($previousTransferItem !== null) {
                TransferItem::withoutGlobalScopes()->whereKey($previousTransferItem->id)->update(['removed_at' => null, 'status' => TransferItemStatus::Expected->value, 'updated_at' => $now]);
            } else {
                $newTransferItems[] = [
                    'transfer_id' => $transfer->id,
                    'box_id' => $box->id,
                    'expected_weight_kg' => $box->current_weight_kg,
                    'received_weight_kg' => null,
                    'status' => TransferItemStatus::Expected->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $movements[] = $this->movementRow($box, MovementType::TransferOut, $admin, $now, $box->current_weight_kg, $box->current_weight_kg, BoxStatus::Available, BoxStatus::InTransit, [
                'from_chamber_id' => $exit->origin_chamber_id,
                'to_chamber_id' => $dest->id,
                'to_branch_id' => $dest->branch_id,
                'exit_id' => $exit->id,
                'transfer_id' => $transfer->id,
                'notes' => "Caja agregada al corregir la transferencia {$transfer->reference}. {$reason}",
            ]);
        }

        $this->insertChunked('exit_items', $newExitItems);
        $this->insertChunked('transfer_items', $newTransferItems);

        $updated = Box::query()->whereIn('id', $boxIds)->where('status', BoxStatus::Available->value)->where('current_chamber_id', $exit->origin_chamber_id)
            ->update(['status' => BoxStatus::InTransit->value, 'current_chamber_id' => null, 'updated_at' => $now]);

        if ($updated !== count($boxIds)) {
            throw new InventoryException('Una o más cajas dejaron de estar disponibles. La corrección fue cancelada.');
        }

        $this->insertChunked('box_movements', $movements);

        return array_values(array_map(fn (Box $box): string => $box->barcode, $boxes->all()));
    }
}
