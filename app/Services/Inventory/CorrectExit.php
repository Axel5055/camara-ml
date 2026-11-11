<?php

namespace App\Services\Inventory;

use App\Enums\BoxStatus;
use App\Enums\DocumentStatus;
use App\Enums\ExitItemKind;
use App\Enums\MovementType;
use App\Exceptions\InventoryException;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\BoxMovement;
use App\Models\Branch;
use App\Models\ExitItem;
use App\Models\StockExit;
use App\Models\User;
use App\Support\Decimal;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CorrectExit extends InventoryOperation
{
    /**
     * Correct an exit registered by mistake (super administrators only).
     *
     * Requester and notes can always change. For plain deliveries (no transfer) the date, the destination branch
     * and the boxes can change too: removed boxes go back to the origin chamber and added ones must be available
     * there; every change is appended to the box histories and the audit log. Exits that created a transfer, or
     * that include partial withdrawals, keep their boxes and date: undo them with a reversal instead.
     *
     * @param  array<int, int|string>  $removeBoxIds
     * @param  array<int, int|string>  $addBoxIds
     */
    public function handle(
        User $admin,
        StockExit $exit,
        string $reason,
        string $requesterName,
        ?string $notes = null,
        ?CarbonInterface $occurredAt = null,
        ?Branch $destBranch = null,
        array $removeBoxIds = [],
        array $addBoxIds = [],
    ): StockExit {
        if (! $admin->canCorrectRecordsOf($exit->originChamber, $exit->destChamber)) {
            throw new AuthorizationException('No tienes permiso para corregir esta salida.');
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

        return DB::transaction(function () use ($admin, $exit, $reason, $requesterName, $notes, $occurredAt, $destBranch, $removeBoxIds, $addBoxIds): StockExit {
            $locked = StockExit::query()->whereKey($exit->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== DocumentStatus::Confirmed) {
                throw new InventoryException("La salida {$locked->reference} fue revertida y no puede corregirse.");
            }

            $transfer = $locked->transfer()->first();
            $items = ExitItem::query()->where('exit_id', $locked->id)->orderBy('id')->get()->keyBy('box_id');
            $activeItems = $items->where('is_active', true);
            $hasPartial = $activeItems->contains(fn (ExitItem $item): bool => $item->kind === ExitItemKind::Partial);
            $boxesLocked = $transfer !== null || $hasPartial;

            if ($boxesLocked && ($removeBoxIds !== [] || $addBoxIds !== [])) {
                throw new InventoryException('Las cajas de una salida con transferencia o con retiros parciales no se modifican; revierte la salida y registra otra.');
            }

            if ($transfer !== null && ($occurredAt !== null && ! $occurredAt->equalTo($locked->occurred_at) || ($destBranch !== null && $destBranch->id !== $locked->dest_branch_id))) {
                throw new InventoryException('La fecha y el destino de una salida con transferencia no se modifican.');
            }

            $newBranch = $destBranch ?? $locked->destBranch()->firstOrFail();

            if ($newBranch->id !== $locked->dest_branch_id && ! $newBranch->is_active) {
                throw new InventoryException("La sucursal {$newBranch->name} está inactiva.");
            }

            $newNotes = $notes === null ? $locked->notes : (trim($notes) === '' ? null : trim($notes));
            $newDate = $occurredAt ?? $locked->occurred_at;
            $now = now();

            $removed = $removeBoxIds === [] ? [] : $this->removeBoxes($admin, $locked, $activeItems, $removeBoxIds, $reason, $now);
            $added = $addBoxIds === [] ? [] : $this->addBoxes($admin, $locked, $newBranch, $items, $addBoxIds, $reason, $now, $newDate);

            $headerChanged = $requesterName !== $locked->requester_name
                || $newNotes !== $locked->notes
                || ! $newDate->equalTo($locked->occurred_at)
                || $newBranch->id !== $locked->dest_branch_id;

            if (! $headerChanged && $removed === [] && $added === []) {
                throw new InventoryException('No hay cambios que guardar.');
            }

            $active = ExitItem::query()->where('exit_id', $locked->id)->where('is_active', true)->get();

            if ($active->isEmpty()) {
                throw new InventoryException('La salida debe conservar al menos una caja; para anularla usa la reversión.');
            }

            $old = $locked->only(['requester_name', 'occurred_at', 'notes', 'dest_branch_id', 'total_boxes', 'total_weight_kg', 'total_cost_mxn']);

            $locked->update([
                'requester_name' => $requesterName,
                'occurred_at' => $newDate,
                'notes' => $newNotes,
                'dest_branch_id' => $newBranch->id,
                'delivery_chamber_id' => $newBranch->id === $locked->dest_branch_id ? $locked->delivery_chamber_id : null,
                'total_boxes' => $active->count(),
                'total_weight_kg' => $active->reduce(fn (string $sum, ExitItem $item): string => Decimal::add($sum, $item->weight_out_kg, Decimal::WEIGHT_SCALE), '0.000'),
                'total_cost_mxn' => $active->reduce(fn (string $sum, ExitItem $item): string => Decimal::add($sum, $item->cost_out_mxn, Decimal::MONEY_SCALE), '0.00'),
            ]);

            AuditLog::record($admin, 'exit.corrected', $locked, $old, [
                ...$locked->only(['requester_name', 'occurred_at', 'notes', 'dest_branch_id', 'total_boxes', 'total_weight_kg', 'total_cost_mxn']),
                'reason' => $reason,
                'removed_boxes' => $removed,
                'added_boxes' => $added,
            ]);

            return $locked->refresh();
        }, 3);
    }

    /**
     * @param  Collection<int, ExitItem>  $activeItems  keyed by box id
     * @param  list<int>  $boxIds
     * @return list<string> barcodes of the boxes that went back to the origin chamber
     */
    private function removeBoxes(User $admin, StockExit $exit, $activeItems, array $boxIds, string $reason, CarbonInterface $now): array
    {
        $foreign = array_diff($boxIds, $activeItems->keys()->all());

        if ($foreign !== []) {
            throw new InventoryException('Cajas que no pertenecen a la salida: '.implode(', ', $foreign).'.');
        }

        $boxes = $this->lockBoxes($boxIds);
        $originals = BoxMovement::query()->where('exit_id', $exit->id)->where('type', MovementType::Exit->value)->pluck('id', 'box_id');
        $movements = [];

        foreach ($boxes as $box) {
            if ($box->status !== BoxStatus::Dispatched) {
                throw new InventoryException("La caja {$box->barcode} ya no está entregada; no se puede quitar de la salida.");
            }

            $movements[] = $this->movementRow($box, MovementType::Reversal, $admin, $now, $box->current_weight_kg, $box->current_weight_kg, BoxStatus::Dispatched, BoxStatus::Available, [
                'to_chamber_id' => $exit->origin_chamber_id,
                'exit_id' => $exit->id,
                'reverses_movement_id' => $originals[$box->id] ?? null,
                'notes' => "Caja quitada al corregir la salida {$exit->reference}. {$reason}",
            ]);
        }

        Box::query()->whereIn('id', $boxIds)->update(['status' => BoxStatus::Available->value, 'current_chamber_id' => $exit->origin_chamber_id, 'updated_at' => $now]);
        ExitItem::query()->where('exit_id', $exit->id)->whereIn('box_id', $boxIds)->update(['is_active' => false, 'updated_at' => $now]);
        $this->insertChunked('box_movements', $movements);

        return array_values(array_map(fn (Box $box): string => $box->barcode, $boxes->all()));
    }

    /**
     * @param  Collection<int, ExitItem>  $existingItems  every item of the exit (active or not), keyed by box id
     * @param  list<int>  $boxIds
     * @return list<string> barcodes of the boxes added to the exit
     */
    private function addBoxes(User $admin, StockExit $exit, Branch $destBranch, $existingItems, array $boxIds, string $reason, CarbonInterface $now, CarbonInterface $date): array
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
        $newItems = [];

        foreach ($boxes as $box) {
            $previous = $existingItems->get($box->id);

            if ($previous !== null) {
                if (Decimal::cmp($previous->weight_before_kg, $box->current_weight_kg, Decimal::WEIGHT_SCALE) !== 0) {
                    throw new InventoryException("La caja {$box->barcode} cambió de peso desde que salió; regístrala en una salida nueva.");
                }

                ExitItem::query()->whereKey($previous->id)->update(['is_active' => true, 'holds_box' => true, 'updated_at' => $now]);
            } else {
                $newItems[] = [
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

            $movements[] = $this->movementRow($box, MovementType::Exit, $admin, $now, $box->current_weight_kg, $box->current_weight_kg, BoxStatus::Available, BoxStatus::Dispatched, [
                'from_chamber_id' => $exit->origin_chamber_id,
                'to_branch_id' => $destBranch->id,
                'exit_id' => $exit->id,
                'notes' => "Caja agregada al corregir la salida {$exit->reference}. {$reason}",
            ]);
        }

        $this->insertChunked('exit_items', $newItems);

        $updated = Box::query()->whereIn('id', $boxIds)->where('status', BoxStatus::Available->value)->where('current_chamber_id', $exit->origin_chamber_id)
            ->update(['status' => BoxStatus::Dispatched->value, 'current_chamber_id' => null, 'updated_at' => $now]);

        if ($updated !== count($boxIds)) {
            throw new InventoryException('Una o más cajas dejaron de estar disponibles. La corrección fue cancelada.');
        }

        $this->insertChunked('box_movements', $movements);

        return array_values(array_map(fn (Box $box): string => $box->barcode, $boxes->all()));
    }
}
