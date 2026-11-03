<?php

namespace App\Services\Inventory;

use App\Enums\BoxStatus;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\MovementType;
use App\Enums\TransferItemStatus;
use App\Enums\TransferStatus;
use App\Exceptions\InventoryException;
use App\Models\Box;
use App\Models\ExitItem;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\User;
use App\Support\Decimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ReceiveTransfer extends InventoryOperation
{
    /**
     * Confirm the physical reception of a transfer in the destination chamber.
     *
     * Boxes listed in $receivedBoxIds move into the destination keeping barcode, weight and cost. Boxes not
     * listed stay "in_transit" with an open "missing" incident. A received weight different from the expected
     * one is recorded as an incident; the box weight itself is only changed through an authorized adjustment.
     *
     * @param  array<int, int|string>  $receivedBoxIds
     * @param  array<int|string, string|int|float>  $receivedWeights  box id => weight measured on reception
     * @param  string|null  $notes  free comments of the person receiving (state of the load, delays, etc.)
     */
    public function handle(User $user, Transfer $transfer, array $receivedBoxIds, array $receivedWeights = [], ?string $notes = null): Transfer
    {
        $transfer->loadMissing('destChamber');
        $this->authorizeChamber($user, $transfer->destChamber);

        $receivedIds = array_map('intval', array_values($receivedBoxIds));

        if ($receivedIds !== array_values(array_unique($receivedIds))) {
            throw new InventoryException('Hay cajas repetidas en la recepción.');
        }

        return DB::transaction(function () use ($user, $transfer, $receivedIds, $receivedWeights, $notes): Transfer {
            $locked = Transfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== TransferStatus::InTransit) {
                throw new InventoryException("La transferencia {$locked->reference} no está en tránsito.");
            }

            $items = TransferItem::query()->where('transfer_id', $locked->id)->get()->keyBy('box_id');

            $foreign = array_diff($receivedIds, $items->keys()->all());

            if ($foreign !== []) {
                throw new InventoryException('Cajas que no pertenecen a la transferencia: '.implode(', ', $foreign).'.');
            }

            $boxes = $this->lockBoxes($items->keys()->all());
            $now = now();
            $incidents = [];
            $movements = [];

            foreach ($boxes as $box) {
                if ($box->status !== BoxStatus::InTransit) {
                    throw new InventoryException("La caja {$box->barcode} no está en tránsito.");
                }
            }

            $missingIds = array_values(array_diff($items->keys()->all(), $receivedIds));

            foreach ($receivedIds as $boxId) {
                $box = $boxes[$boxId];
                $item = $items[$boxId];
                $measured = isset($receivedWeights[$boxId])
                    ? Decimal::normalize($receivedWeights[$boxId], Decimal::WEIGHT_SCALE)
                    : $item->expected_weight_kg;

                $item->update(['status' => TransferItemStatus::Received, 'received_weight_kg' => $measured]);

                if (Decimal::cmp($measured, $item->expected_weight_kg, Decimal::WEIGHT_SCALE) !== 0) {
                    $incidents[] = $this->incidentRow($locked, $user, $boxId, IncidentType::WeightDifference, $item->expected_weight_kg, $measured,
                        "Peso esperado {$item->expected_weight_kg} kg, recibido {$measured} kg ({$box->barcode}).", $now);
                }

                $movements[] = $this->movementRow(
                    $box, MovementType::TransferIn, $user, $now, $box->current_weight_kg, $box->current_weight_kg,
                    BoxStatus::InTransit, BoxStatus::Available,
                    ['from_chamber_id' => $locked->origin_chamber_id, 'to_chamber_id' => $locked->dest_chamber_id, 'transfer_id' => $locked->id],
                );
            }

            foreach ($missingIds as $boxId) {
                $items[$boxId]->update(['status' => TransferItemStatus::Missing]);
                $incidents[] = $this->incidentRow($locked, $user, $boxId, IncidentType::Missing, $items[$boxId]->expected_weight_kg, null,
                    "Caja {$boxes[$boxId]->barcode} no recibida.", $now);
            }

            if ($receivedIds !== []) {
                $updated = Box::query()->whereIn('id', $receivedIds)->where('status', BoxStatus::InTransit->value)
                    ->update(['status' => BoxStatus::Available->value, 'current_chamber_id' => $locked->dest_chamber_id, 'updated_at' => $now]);

                if ($updated !== count($receivedIds)) {
                    throw new InventoryException('No fue posible recibir todas las cajas. La recepción fue cancelada.');
                }

                // The boxes are back in stock: their exit no longer keeps them out, so they can leave again.
                ExitItem::query()->where('exit_id', $locked->exit_id)->whereIn('box_id', $receivedIds)
                    ->update(['holds_box' => false, 'updated_at' => $now]);
            }

            $this->insertChunked('box_movements', $movements);

            if ($incidents !== []) {
                $this->insertChunked('incidents', $incidents);
            }

            $locked->update([
                'status' => TransferStatus::Received,
                'received_at' => $now,
                'received_by' => $user->id,
                'receive_notes' => filled($notes) ? trim($notes) : null,
            ]);

            return $locked->refresh();
        }, 3);
    }

    /**
     * @return array<string, mixed>
     */
    private function incidentRow(Transfer $transfer, User $user, int $boxId, IncidentType $type, ?string $expected, ?string $received, string $description, CarbonInterface $now): array
    {
        return [
            'transfer_id' => $transfer->id,
            'box_id' => $boxId,
            'type' => $type->value,
            'expected_weight_kg' => $expected,
            'received_weight_kg' => $received,
            'description' => $description,
            'status' => IncidentStatus::Open->value,
            'reported_by' => $user->id,
            'resolved_by' => null,
            'resolved_at' => null,
            'resolution' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
