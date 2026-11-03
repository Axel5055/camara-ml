<?php

namespace App\Services\Inventory;

use App\Enums\BoxStatus;
use App\Enums\DocumentStatus;
use App\Enums\ExitItemKind;
use App\Enums\MovementType;
use App\Enums\TransferItemStatus;
use App\Enums\TransferStatus;
use App\Exceptions\InventoryException;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\StockExit;
use App\Models\Transfer;
use App\Models\User;
use App\Services\Barcodes\SequenceAllocator;
use App\Support\Decimal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterExit extends InventoryOperation
{
    public function __construct(private SequenceAllocator $sequences) {}

    /**
     * Withdraw exactly the selected boxes from a chamber.
     *
     * Without a destination chamber the boxes are delivered (status "dispatched"). With one, a transfer is
     * created and the boxes stay "in_transit" until the destination confirms the reception.
     *
     * @param  array<int, int|string>  $boxIds
     */
    public function handle(
        User $user,
        Chamber $origin,
        Branch $destBranch,
        ?Chamber $destChamber,
        string $requesterName,
        array $boxIds,
        ?CarbonInterface $occurredAt = null,
        ?string $notes = null,
        ?string $clientToken = null,
    ): StockExit {
        $this->authorizeChamber($user, $origin);

        $requesterName = trim($requesterName);

        if ($requesterName === '') {
            throw new InventoryException('Indica quién solicita la mercancía.');
        }

        $boxIds = $this->uniqueIds($boxIds);

        if (! $destBranch->is_active) {
            throw new InventoryException("La sucursal {$destBranch->name} está inactiva.");
        }

        if ($destChamber !== null) {
            if ($destChamber->branch_id !== $destBranch->id) {
                throw new InventoryException('La cámara destino no pertenece a la sucursal destino.');
            }

            if (! $destChamber->is_active) {
                throw new InventoryException("La cámara {$destChamber->name} está inactiva.");
            }

            if ($destChamber->is($origin)) {
                throw new InventoryException('La cámara destino debe ser distinta a la de origen.');
            }
        }

        $clientToken ??= (string) Str::uuid();

        if ($existing = StockExit::query()->where('client_token', $clientToken)->first()) {
            return $existing;
        }

        $reference = $this->sequences->reference('SAL', $this->sequences->next('exit'));
        $transferReference = $destChamber ? $this->sequences->reference('TRF', $this->sequences->next('transfer')) : null;
        $occurredAt ??= now();

        return DB::transaction(function () use ($user, $origin, $destBranch, $destChamber, $requesterName, $boxIds, $occurredAt, $notes, $clientToken, $reference, $transferReference): StockExit {
            $boxes = $this->lockBoxes($boxIds);

            $this->assertAllAvailableIn($boxes, $boxIds, $origin);

            $exit = StockExit::create([
                'reference' => $reference,
                'requester_name' => $requesterName,
                'origin_chamber_id' => $origin->id,
                'dest_branch_id' => $destBranch->id,
                'dest_chamber_id' => $destChamber?->id,
                'user_id' => $user->id,
                'occurred_at' => $occurredAt,
                'total_boxes' => $boxes->count(),
                'total_weight_kg' => $boxes->reduce(fn (string $sum, Box $b): string => Decimal::add($sum, $b->current_weight_kg, Decimal::WEIGHT_SCALE), '0.000'),
                'total_cost_mxn' => $boxes->reduce(fn (string $sum, Box $b): string => Decimal::add($sum, $b->unit_cost_mxn, Decimal::MONEY_SCALE), '0.00'),
                'notes' => $notes,
                'status' => DocumentStatus::Confirmed,
                'client_token' => $clientToken,
            ]);

            $now = now();
            $this->insertChunked('exit_items', $boxes->map(fn (Box $b): array => [
                'exit_id' => $exit->id,
                'box_id' => $b->id,
                'kind' => ExitItemKind::Full->value,
                'weight_before_kg' => $b->current_weight_kg,
                'weight_out_kg' => $b->current_weight_kg,
                'weight_after_kg' => '0.000',
                'unit_cost_snapshot_mxn' => $b->unit_cost_mxn,
                'cost_out_mxn' => $b->unit_cost_mxn,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all());

            $transfer = null;

            if ($destChamber !== null) {
                $transfer = Transfer::create([
                    'reference' => $transferReference,
                    'exit_id' => $exit->id,
                    'origin_chamber_id' => $origin->id,
                    'dest_chamber_id' => $destChamber->id,
                    'status' => TransferStatus::InTransit,
                    'dispatched_at' => $occurredAt,
                ]);

                $this->insertChunked('transfer_items', $boxes->map(fn (Box $b): array => [
                    'transfer_id' => $transfer->id,
                    'box_id' => $b->id,
                    'expected_weight_kg' => $b->current_weight_kg,
                    'received_weight_kg' => null,
                    'status' => TransferItemStatus::Expected->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all());
            }

            $newStatus = $destChamber ? BoxStatus::InTransit : BoxStatus::Dispatched;

            $updated = Box::query()
                ->whereIn('id', $boxIds)
                ->where('status', BoxStatus::Available->value)
                ->where('current_chamber_id', $origin->id)
                ->update(['status' => $newStatus->value, 'current_chamber_id' => null, 'updated_at' => $now]);

            if ($updated !== count($boxIds)) {
                throw new InventoryException('Una o más cajas dejaron de estar disponibles. La salida fue cancelada.');
            }

            $this->insertChunked('box_movements', $boxes->map(fn (Box $b): array => $this->movementRow(
                $b,
                $destChamber ? MovementType::TransferOut : MovementType::Exit,
                $user,
                $occurredAt,
                $b->current_weight_kg,
                $b->current_weight_kg,
                BoxStatus::Available,
                $newStatus,
                [
                    'from_chamber_id' => $origin->id,
                    'to_chamber_id' => $destChamber?->id,
                    'to_branch_id' => $destBranch->id,
                    'exit_id' => $exit->id,
                    'transfer_id' => $transfer?->id,
                ],
            ))->values()->all());

            return $exit->refresh();
        }, 3);
    }

    /**
     * @param  Collection<int, Box>  $boxes
     * @param  list<int>  $requestedIds
     */
    private function assertAllAvailableIn($boxes, array $requestedIds, Chamber $origin): void
    {
        $missing = array_diff($requestedIds, $boxes->keys()->all());

        if ($missing !== []) {
            throw new InventoryException('Cajas inexistentes: '.implode(', ', $missing).'.');
        }

        $unavailable = $boxes->filter(fn (Box $b): bool => $b->status !== BoxStatus::Available || $b->current_chamber_id !== $origin->id);

        if ($unavailable->isNotEmpty()) {
            throw new InventoryException('Cajas no disponibles en la cámara de origen: '.$unavailable->pluck('barcode')->implode(', ').'.');
        }
    }
}
