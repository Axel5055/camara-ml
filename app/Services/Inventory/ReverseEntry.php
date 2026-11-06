<?php

namespace App\Services\Inventory;

use App\Enums\BoxStatus;
use App\Enums\DocumentStatus;
use App\Enums\MovementType;
use App\Exceptions\InventoryException;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\Entry;
use App\Models\EntryItem;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ReverseEntry extends InventoryOperation
{
    /**
     * Cancel an entry registered by mistake (super administrators only).
     *
     * Only while every box of the entry is still intact in its chamber (available, full weight): they leave the
     * stock as written-off, voided boxes. Nothing is deleted: the entry is marked reversed, a movement is appended
     * to each box history and the audit log keeps the reason.
     */
    public function handle(User $admin, Entry $entry, string $reason): Entry
    {
        if (! $admin->isSuperAdmin()) {
            throw new AuthorizationException('Solo el super administrador puede cancelar una entrada.');
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw new InventoryException('Indica el motivo de la cancelación (mínimo 5 caracteres).');
        }

        return DB::transaction(function () use ($admin, $entry, $reason): Entry {
            $locked = Entry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== DocumentStatus::Confirmed) {
                throw new InventoryException("La entrada {$locked->reference} ya fue cancelada.");
            }

            $itemIds = EntryItem::query()->where('entry_id', $locked->id)->pluck('id');
            $boxes = Box::query()->whereIn('entry_item_id', $itemIds)->whereNull('voided_at')->orderBy('id')->lockForUpdate()->get();

            $moved = $boxes->filter(fn (Box $box): bool => $box->status !== BoxStatus::Available
                || $box->current_chamber_id !== $locked->chamber_id
                || Decimal::cmp($box->current_weight_kg, $box->initial_weight_kg, Decimal::WEIGHT_SCALE) !== 0);

            if ($moved->isNotEmpty()) {
                throw new InventoryException('Ya hubo movimientos con cajas de esta entrada; no se puede cancelar: '.$moved->pluck('barcode')->take(5)->implode(', ').($moved->count() > 5 ? '…' : '').'. Usa un ajuste autorizado.');
            }

            $now = now();

            Box::query()->whereIn('id', $boxes->modelKeys())->update([
                'status' => BoxStatus::WrittenOff->value, 'current_chamber_id' => null, 'voided_at' => $now, 'updated_at' => $now,
            ]);

            $this->insertChunked('box_movements', $boxes->map(fn (Box $box): array => $this->movementRow(
                $box,
                MovementType::Reversal,
                $admin,
                $now,
                $box->current_weight_kg,
                $box->current_weight_kg,
                BoxStatus::Available,
                BoxStatus::WrittenOff,
                [
                    'from_chamber_id' => $locked->chamber_id,
                    'entry_id' => $locked->id,
                    'notes' => "Entrada {$locked->reference} cancelada. {$reason}",
                ],
            ))->values()->all());

            $locked->update(['status' => DocumentStatus::Reversed]);

            AuditLog::record($admin, 'entry.reversed', $locked, ['status' => DocumentStatus::Confirmed->value], [
                'status' => DocumentStatus::Reversed->value,
                'reason' => $reason,
                'voided_boxes' => $boxes->count(),
            ]);

            return $locked->refresh();
        }, 3);
    }
}
