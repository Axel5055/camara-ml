<?php

namespace App\Services\Inventory;

use App\Enums\BoxStatus;
use App\Enums\DocumentStatus;
use App\Enums\ExitItemKind;
use App\Enums\MovementType;
use App\Exceptions\InventoryException;
use App\Models\Box;
use App\Models\Branch;
use App\Models\StockExit;
use App\Models\User;
use App\Services\Barcodes\SequenceAllocator;
use App\Support\Decimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WithdrawPartial extends InventoryOperation
{
    public function __construct(private SequenceAllocator $sequences) {}

    /**
     * Take part of the content of a box. The box keeps its barcode and remaining weight; it is only
     * considered gone (depleted) when nothing is left. Destination is always a branch (never a chamber),
     * so a physical box is never split into two inventory records.
     */
    public function handle(
        User $user,
        Box $box,
        string|int|float $weightOut,
        Branch $destBranch,
        string $requesterName,
        ?CarbonInterface $occurredAt = null,
        ?string $notes = null,
        ?string $clientToken = null,
    ): StockExit {
        $chamber = $box->currentChamber ?? throw new InventoryException("La caja {$box->barcode} no está en ninguna cámara.");
        $this->authorizeChamber($user, $chamber);

        $weightOut = Decimal::normalize($weightOut, Decimal::WEIGHT_SCALE);
        $requesterName = trim($requesterName);

        if ($requesterName === '') {
            throw new InventoryException('Indica quién solicita la mercancía.');
        }

        if (Decimal::cmp($weightOut, '0', Decimal::WEIGHT_SCALE) <= 0) {
            throw new InventoryException('El peso a retirar debe ser mayor a cero.');
        }

        if (! $destBranch->is_active) {
            throw new InventoryException("La sucursal {$destBranch->name} está inactiva.");
        }

        $clientToken ??= (string) Str::uuid();

        if ($existing = StockExit::query()->where('client_token', $clientToken)->first()) {
            return $existing;
        }

        $reference = $this->sequences->reference('SAL', $this->sequences->next('exit'));
        $occurredAt ??= now();

        return DB::transaction(function () use ($user, $box, $chamber, $weightOut, $destBranch, $requesterName, $occurredAt, $notes, $clientToken, $reference): StockExit {
            $locked = $this->lockBoxes([$box->id])->first() ?? throw new InventoryException('La caja no existe.');

            if ($locked->status !== BoxStatus::Available || $locked->current_chamber_id !== $chamber->id) {
                throw new InventoryException("La caja {$locked->barcode} ya no está disponible en la cámara.");
            }

            if (Decimal::cmp($weightOut, $locked->current_weight_kg, Decimal::WEIGHT_SCALE) > 0) {
                throw new InventoryException("La caja {$locked->barcode} solo contiene {$locked->current_weight_kg} kg.");
            }

            $before = $locked->current_weight_kg;
            $after = Decimal::sub($before, $weightOut, Decimal::WEIGHT_SCALE);
            $cost = Decimal::costOf($weightOut, $locked->price_per_kg_mxn);
            $depleted = Decimal::cmp($after, '0', Decimal::WEIGHT_SCALE) === 0;
            $newStatus = $depleted ? BoxStatus::Depleted : BoxStatus::Available;

            $exit = StockExit::create([
                'reference' => $reference,
                'requester_name' => $requesterName,
                'origin_chamber_id' => $chamber->id,
                'dest_branch_id' => $destBranch->id,
                'dest_chamber_id' => null,
                'user_id' => $user->id,
                'occurred_at' => $occurredAt,
                'total_boxes' => 1,
                'total_weight_kg' => $weightOut,
                'total_cost_mxn' => $cost,
                'notes' => $notes,
                'status' => DocumentStatus::Confirmed,
                'client_token' => $clientToken,
            ]);

            $exit->items()->create([
                'box_id' => $locked->id,
                'kind' => ExitItemKind::Partial,
                'weight_before_kg' => $before,
                'weight_out_kg' => $weightOut,
                'weight_after_kg' => $after,
                'unit_cost_snapshot_mxn' => $locked->unit_cost_mxn,
                'cost_out_mxn' => $cost,
                'is_active' => true,
            ]);

            $locked->update([
                'current_weight_kg' => $after,
                'status' => $newStatus,
                'current_chamber_id' => $depleted ? null : $chamber->id,
            ]);

            $this->insertChunked('box_movements', [$this->movementRow(
                $locked,
                MovementType::PartialWithdrawal,
                $user,
                $occurredAt,
                $before,
                $after,
                BoxStatus::Available,
                $newStatus,
                ['from_chamber_id' => $chamber->id, 'to_branch_id' => $destBranch->id, 'exit_id' => $exit->id],
            )]);

            return $exit->refresh();
        }, 3);
    }
}
