<?php

namespace App\Services\Inventory;

use App\Enums\BarcodeSource;
use App\Enums\BoxStatus;
use App\Enums\DocumentStatus;
use App\Enums\MovementType;
use App\Exceptions\InventoryException;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\Entry;
use App\Models\EntryItem;
use App\Models\User;
use App\Services\Barcodes\BarcodeGenerator;
use App\Services\Barcodes\SequenceAllocator;
use App\Support\Decimal;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CorrectEntry extends InventoryOperation
{
    public function __construct(
        private SequenceAllocator $sequences,
        private BarcodeGenerator $barcodes,
    ) {}

    /**
     * Correct an entry registered by mistake (super administrators only).
     *
     * The date and notes can always change. The boxes, weight per box and cost of a group can only change while
     * every active box of that group is still intact in the entry's chamber (available, full weight): the stock is
     * updated and every change is appended to each box history and to the audit log. Removed boxes are not
     * deleted: they are written off and marked as voided; added boxes get new generated barcodes.
     *
     * @param  array<int|string, array{boxes_count: int|string, weight_per_box_kg: string|int|float, price_per_kg_mxn: string|int|float}>  $items  entry item id => new values
     */
    public function handle(User $admin, Entry $entry, string $reason, ?CarbonInterface $occurredAt = null, ?string $notes = null, array $items = []): Entry
    {
        if (! $admin->canCorrectEntriesOf($entry->chamber)) {
            throw new AuthorizationException('No tienes permiso para corregir esta entrada.');
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw new InventoryException('Indica el motivo de la corrección (mínimo 5 caracteres).');
        }

        $changes = $this->normalize($entry, $items);
        $needed = array_sum(array_map(fn (array $change): int => max(0, $change['boxes_count'] - $change['old_boxes_count']), $changes));
        $firstNumber = $needed > 0 ? $this->sequences->next('barcode', $needed) : 0;
        $createdOn = now();

        return DB::transaction(function () use ($admin, $entry, $reason, $occurredAt, $notes, $changes, $needed, $firstNumber, $createdOn): Entry {
            $locked = Entry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== DocumentStatus::Confirmed) {
                throw new InventoryException("La entrada {$locked->reference} fue revertida y no puede corregirse.");
            }

            $items = EntryItem::query()->where('entry_id', $locked->id)->orderBy('id')->get()->keyBy('id');
            $changes = array_filter($changes, fn (array $change): bool => $this->differs($change, $items[$change['id']] ?? throw new InventoryException('El grupo no pertenece a la entrada.')));
            $stillNeeded = array_sum(array_map(fn (array $change): int => max(0, $change['boxes_count'] - $items[$change['id']]->boxes_count), $changes));

            if ($stillNeeded > $needed) {
                throw new InventoryException('La entrada cambió mientras se corregía. Inténtalo de nuevo.');
            }

            $newNotes = $notes === null ? $locked->notes : (trim($notes) === '' ? null : trim($notes));
            $newDate = $occurredAt ?? $locked->occurred_at;
            $headerChanged = ! $newDate->equalTo($locked->occurred_at) || $newNotes !== $locked->notes;

            if ($changes === [] && ! $headerChanged) {
                throw new InventoryException('No hay cambios que guardar.');
            }

            $now = now();
            $number = $firstNumber;
            $audit = [];

            foreach ($changes as $change) {
                $item = $items[$change['id']];
                $audit[] = $this->applyChange($admin, $locked, $item, $change, $reason, $newDate, $number, $createdOn, $now);
            }

            if (! $newDate->equalTo($locked->occurred_at)) {
                Box::query()->whereIn('entry_item_id', $items->keys())->update(['entered_at' => $newDate, 'updated_at' => $now]);
            }

            $fresh = EntryItem::query()->where('entry_id', $locked->id)->get();
            $old = $locked->only(['occurred_at', 'notes', 'total_boxes', 'total_weight_kg', 'total_cost_mxn']);

            $locked->update([
                'occurred_at' => $newDate,
                'notes' => $newNotes,
                'total_boxes' => (int) $fresh->sum('boxes_count'),
                'total_weight_kg' => $fresh->reduce(fn (string $sum, EntryItem $item): string => Decimal::add($sum, $item->total_weight_kg, Decimal::WEIGHT_SCALE), '0.000'),
                'total_cost_mxn' => $fresh->reduce(fn (string $sum, EntryItem $item): string => Decimal::add($sum, $item->total_cost_mxn, Decimal::MONEY_SCALE), '0.00'),
            ]);

            AuditLog::record($admin, 'entry.corrected', $locked, $old, [
                ...$locked->only(['occurred_at', 'notes', 'total_boxes', 'total_weight_kg', 'total_cost_mxn']),
                'reason' => $reason,
                'groups' => $audit,
            ]);

            return $locked->refresh();
        }, 3);
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $items
     * @return list<array{id: int, boxes_count: int, old_boxes_count: int, weight_per_box_kg: string, price_per_kg_mxn: string, unit_cost_mxn: string}>
     */
    private function normalize(Entry $entry, array $items): array
    {
        $current = EntryItem::query()->where('entry_id', $entry->id)->get()->keyBy('id');
        $normalized = [];

        foreach ($items as $id => $values) {
            $item = $current[(int) $id] ?? throw new InventoryException('El grupo no pertenece a la entrada.');

            $count = (int) ($values['boxes_count'] ?? 0);
            $weight = Decimal::normalize($values['weight_per_box_kg'] ?? '', Decimal::WEIGHT_SCALE);
            $price = Decimal::normalize($values['price_per_kg_mxn'] ?? '', Decimal::MONEY_SCALE);

            if ($count < 1 || $count > 100000) {
                throw new InventoryException('La cantidad de cajas debe estar entre 1 y 100,000.');
            }

            if (Decimal::cmp($weight, '0', Decimal::WEIGHT_SCALE) <= 0) {
                throw new InventoryException('El peso por caja debe ser mayor a cero.');
            }

            if (Decimal::cmp($price, '0', Decimal::MONEY_SCALE) < 0) {
                throw new InventoryException('El costo por kilo no puede ser negativo.');
            }

            $normalized[] = [
                'id' => $item->id,
                'boxes_count' => $count,
                'old_boxes_count' => $item->boxes_count,
                'weight_per_box_kg' => $weight,
                'price_per_kg_mxn' => $price,
                'unit_cost_mxn' => Decimal::costOf($weight, $price),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array{boxes_count: int, weight_per_box_kg: string, price_per_kg_mxn: string}  $change
     */
    private function differs(array $change, EntryItem $item): bool
    {
        return $change['boxes_count'] !== $item->boxes_count
            || Decimal::cmp($change['weight_per_box_kg'], $item->weight_per_box_kg, Decimal::WEIGHT_SCALE) !== 0
            || Decimal::cmp($change['price_per_kg_mxn'], $item->price_per_kg_mxn, Decimal::MONEY_SCALE) !== 0;
    }

    /**
     * @param  array{id: int, boxes_count: int, weight_per_box_kg: string, price_per_kg_mxn: string, unit_cost_mxn: string}  $change
     * @return array<string, mixed> what changed in the group, for the audit log
     */
    private function applyChange(User $admin, Entry $entry, EntryItem $item, array $change, string $reason, CarbonInterface $date, int &$number, CarbonInterface $createdOn, CarbonInterface $now): array
    {
        $boxes = Box::query()->where('entry_item_id', $item->id)->whereNull('voided_at')->orderBy('id')->lockForUpdate()->get();

        $moved = $boxes->filter(fn (Box $box): bool => $box->status !== BoxStatus::Available
            || $box->current_chamber_id !== $entry->chamber_id
            || Decimal::cmp($box->current_weight_kg, $box->initial_weight_kg, Decimal::WEIGHT_SCALE) !== 0);

        if ($moved->isNotEmpty()) {
            throw new InventoryException('Ya hubo movimientos con cajas de este grupo; no se puede corregir: '.$moved->pluck('barcode')->take(5)->implode(', ').($moved->count() > 5 ? '…' : '').'. Usa un ajuste autorizado.');
        }

        $item->loadMissing('product');
        $weight = $change['weight_per_box_kg'];
        $price = $change['price_per_kg_mxn'];
        $cost = $change['unit_cost_mxn'];
        $reference = $entry->reference;
        $summary = [
            'item_id' => $item->id,
            'before' => $item->only(['boxes_count', 'weight_per_box_kg', 'price_per_kg_mxn', 'unit_cost_mxn']),
            'after' => ['boxes_count' => $change['boxes_count'], 'weight_per_box_kg' => $weight, 'price_per_kg_mxn' => $price, 'unit_cost_mxn' => $cost],
        ];
        $movements = [];

        // Weight and cost of the boxes that stay.
        $keep = $boxes->take(min($boxes->count(), $change['boxes_count']));
        $valuesChanged = Decimal::cmp($weight, $item->weight_per_box_kg, Decimal::WEIGHT_SCALE) !== 0
            || Decimal::cmp($price, $item->price_per_kg_mxn, Decimal::MONEY_SCALE) !== 0;

        if ($valuesChanged && $keep->isNotEmpty()) {
            Box::query()->whereIn('id', $keep->modelKeys())->update([
                'initial_weight_kg' => $weight, 'current_weight_kg' => $weight, 'price_per_kg_mxn' => $price, 'unit_cost_mxn' => $cost, 'updated_at' => $now,
            ]);

            foreach ($keep as $box) {
                $movements[] = $this->movementRow($box, MovementType::Adjustment, $admin, $now, $box->current_weight_kg, $weight, BoxStatus::Available, BoxStatus::Available, [
                    'to_chamber_id' => $entry->chamber_id,
                    'entry_id' => $entry->id,
                    'notes' => "Corrección de la entrada {$reference}: peso {$item->weight_per_box_kg} → {$weight} kg, costo por kg {$item->price_per_kg_mxn} → {$price}. {$reason}",
                ]);
            }
        }

        // Boxes removed by the correction are written off, never deleted.
        $remove = $boxes->slice($keep->count());

        if ($remove->isNotEmpty()) {
            Box::query()->whereIn('id', $remove->modelKeys())->update([
                'status' => BoxStatus::WrittenOff->value, 'current_chamber_id' => null, 'voided_at' => $now, 'updated_at' => $now,
            ]);

            foreach ($remove as $box) {
                $movements[] = $this->movementRow($box, MovementType::Adjustment, $admin, $now, $box->current_weight_kg, $box->current_weight_kg, BoxStatus::Available, BoxStatus::WrittenOff, [
                    'from_chamber_id' => $entry->chamber_id,
                    'entry_id' => $entry->id,
                    'notes' => "Caja anulada al corregir la entrada {$reference}. {$reason}",
                ]);
            }

            $summary['voided_boxes'] = $remove->pluck('barcode')->all();
        }

        // Boxes added by the correction.
        $add = $change['boxes_count'] - $boxes->count();

        if ($add > 0) {
            $rows = [];

            for ($i = 0; $i < $add; $i++) {
                $rows[] = [
                    'barcode' => $this->barcodes->generate($item->product, $number++, $createdOn),
                    'barcode_source' => BarcodeSource::Generated->value,
                    'manual_barcode_origin' => null,
                    'product_id' => $item->product_id,
                    'category_id' => $item->product->category_id,
                    'entry_item_id' => $item->id,
                    'origin_chamber_id' => $entry->chamber_id,
                    'initial_weight_kg' => $weight,
                    'current_weight_kg' => $weight,
                    'price_per_kg_mxn' => $price,
                    'unit_cost_mxn' => $cost,
                    'entered_at' => $date,
                    'status' => BoxStatus::Available->value,
                    'current_chamber_id' => $entry->chamber_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $this->insertChunked('boxes', $rows);

            $newBoxes = Box::query()->whereIn('barcode', array_column($rows, 'barcode'))->orderBy('id')->get();

            foreach ($newBoxes as $box) {
                $movements[] = $this->movementRow($box, MovementType::Entry, $admin, $now, '0.000', $weight, null, BoxStatus::Available, [
                    'to_chamber_id' => $entry->chamber_id,
                    'entry_id' => $entry->id,
                    'notes' => "Caja agregada al corregir la entrada {$reference}. {$reason}",
                ]);
            }

            $summary['added_boxes'] = $newBoxes->pluck('barcode')->all();
        }

        $this->insertChunked('box_movements', $movements);

        $item->update([
            'boxes_count' => $change['boxes_count'],
            'weight_per_box_kg' => $weight,
            'price_per_kg_mxn' => $price,
            'unit_cost_mxn' => $cost,
            'total_weight_kg' => Decimal::mul($weight, (string) $change['boxes_count'], Decimal::WEIGHT_SCALE),
            'total_cost_mxn' => Decimal::mul($cost, (string) $change['boxes_count'], Decimal::MONEY_SCALE),
        ]);

        return $summary;
    }
}
