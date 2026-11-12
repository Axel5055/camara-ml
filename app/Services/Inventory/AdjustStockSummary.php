<?php

namespace App\Services\Inventory;

use App\Enums\BarcodeSource;
use App\Enums\BoxStatus;
use App\Enums\MovementType;
use App\Exceptions\InventoryException;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\Chamber;
use App\Models\Product;
use App\Models\User;
use App\Services\Barcodes\BarcodeGenerator;
use App\Services\Barcodes\SequenceAllocator;
use App\Support\Decimal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AdjustStockSummary extends InventoryOperation
{
    public function __construct(private SequenceAllocator $sequences, private BarcodeGenerator $barcodes) {}

    /**
     * Settle the stock of a product in a chamber to the numbers the super administrator counted (super administrators
     * only). Every box stays traceable: surplus boxes (the oldest first) are written off, missing boxes are created
     * with the given or the average weight and price per kilo, and the new weight / price are applied to the boxes that remain
     * (the weight only to intact boxes, so partial withdrawals are never undone). Each change is appended to the box
     * histories and to the audit log with the reason.
     *
     * @return array{written_off: int, added: int, reweighed: int, recosted: int, skipped_partial: int}
     */
    public function handle(
        User $admin,
        Chamber $chamber,
        Product $product,
        string $reason,
        ?int $totalBoxes = null,
        string|int|float|null $weightPerBox = null,
        string|int|float|null $pricePerKg = null,
    ): array {
        if (! $admin->isSuperAdmin()) {
            throw new AuthorizationException('Solo el super administrador puede ajustar las existencias.');
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw new InventoryException('Indica el motivo del ajuste (mínimo 5 caracteres).');
        }

        if ($totalBoxes !== null && ($totalBoxes < 0 || $totalBoxes > 100000)) {
            throw new InventoryException('La cantidad de cajas debe estar entre 0 y 100,000.');
        }

        $weight = $weightPerBox === null ? null : Decimal::normalize($weightPerBox, Decimal::WEIGHT_SCALE);
        $price = $pricePerKg === null ? null : Decimal::normalize($pricePerKg, Decimal::MONEY_SCALE);

        if ($weight !== null && Decimal::cmp($weight, '0', Decimal::WEIGHT_SCALE) <= 0) {
            throw new InventoryException('El peso por caja debe ser mayor a cero.');
        }

        if ($price !== null && Decimal::cmp($price, '0', Decimal::MONEY_SCALE) < 0) {
            throw new InventoryException('El costo por kilo no puede ser negativo.');
        }

        if ($totalBoxes === null && $weight === null && $price === null) {
            throw new InventoryException('No hay cambios que guardar.');
        }

        $currentCount = $this->group($chamber, $product)->count();
        $needed = $totalBoxes !== null ? max(0, $totalBoxes - $currentCount) : 0;
        $firstNumber = $needed > 0 ? $this->sequences->next('barcode', $needed) : 0;
        $createdOn = now();

        return DB::transaction(function () use ($admin, $chamber, $product, $reason, $totalBoxes, $weight, $price, $needed, $firstNumber, $createdOn): array {
            $boxes = $this->group($chamber, $product, lock: true);

            if ($boxes->isEmpty()) {
                throw new InventoryException('No hay cajas disponibles de este producto en la cámara.');
            }

            $count = $boxes->count();
            $stillNeeded = $totalBoxes !== null ? max(0, $totalBoxes - $count) : 0;

            if ($stillNeeded > $needed) {
                throw new InventoryException('Las existencias cambiaron mientras se ajustaban. Inténtalo de nuevo.');
            }

            $before = $this->snapshot($boxes);
            $now = now();
            $movements = [];
            $result = ['written_off' => 0, 'added' => 0, 'reweighed' => 0, 'recosted' => 0, 'skipped_partial' => 0];
            $name = $product->name;

            $averageWeight = $this->average($boxes->map(fn (Box $box): string => $box->current_weight_kg)->values()->all(), Decimal::WEIGHT_SCALE);
            $averagePrice = $this->average($boxes->map(fn (Box $box): string => $box->price_per_kg_mxn)->values()->all(), Decimal::MONEY_SCALE);
            $template = $boxes->sortByDesc(fn (Box $box): string => $box->entered_at->format('YmdHis').str_pad((string) $box->id, 12, '0', STR_PAD_LEFT))->first();

            // Surplus boxes leave the stock (the oldest first); they are written off, never deleted.
            $keep = $boxes;

            if ($totalBoxes !== null && $totalBoxes < $count) {
                $remove = $boxes->take($count - $totalBoxes);
                $keep = $boxes->slice($remove->count());

                Box::query()->whereIn('id', $remove->modelKeys())->update([
                    'status' => BoxStatus::WrittenOff->value, 'current_chamber_id' => null, 'updated_at' => $now,
                ]);

                foreach ($remove as $box) {
                    $movements[] = $this->movementRow($box, MovementType::Adjustment, $admin, $now, $box->current_weight_kg, $box->current_weight_kg, BoxStatus::Available, BoxStatus::WrittenOff, [
                        'from_chamber_id' => $chamber->id,
                        'notes' => "Ajuste de existencias de {$name}: baja de la caja. {$reason}",
                    ]);
                }

                $result['written_off'] = $remove->count();
            }

            // New weight and cost for the boxes that stay.
            foreach ($keep as $box) {
                $intact = Decimal::cmp($box->current_weight_kg, $box->initial_weight_kg, Decimal::WEIGHT_SCALE) === 0;
                $update = [];
                $notes = [];

                if ($weight !== null && $intact && Decimal::cmp($weight, $box->current_weight_kg, Decimal::WEIGHT_SCALE) !== 0) {
                    $update += ['initial_weight_kg' => $weight, 'current_weight_kg' => $weight];
                    $notes[] = "peso {$box->current_weight_kg} → {$weight} kg";
                    $result['reweighed']++;
                } elseif ($weight !== null && ! $intact) {
                    $result['skipped_partial']++;
                }

                if ($price !== null && Decimal::cmp($price, $box->price_per_kg_mxn, Decimal::MONEY_SCALE) !== 0) {
                    $update += ['price_per_kg_mxn' => $price];
                    $notes[] = "costo por kg {$box->price_per_kg_mxn} → {$price}";
                    $result['recosted']++;
                }

                // The cost of the box follows its initial weight and its price per kilo.
                if (isset($update['price_per_kg_mxn']) || isset($update['initial_weight_kg'])) {
                    $update['unit_cost_mxn'] = Decimal::costOf(
                        $update['initial_weight_kg'] ?? $box->initial_weight_kg,
                        $update['price_per_kg_mxn'] ?? $box->price_per_kg_mxn,
                    );
                }

                if ($update === []) {
                    continue;
                }

                Box::query()->whereKey($box->id)->update($update + ['updated_at' => $now]);

                $movements[] = $this->movementRow($box, MovementType::Adjustment, $admin, $now, $box->current_weight_kg, $update['current_weight_kg'] ?? $box->current_weight_kg, BoxStatus::Available, BoxStatus::Available, [
                    'to_chamber_id' => $chamber->id,
                    'notes' => "Ajuste de existencias de {$name}: ".implode(', ', $notes).". {$reason}",
                ]);
            }

            // Missing boxes are created with the counted (or the average) weight and price per kilo.
            if ($totalBoxes !== null && $totalBoxes > $count) {
                $newWeight = $weight ?? $averageWeight;
                $newPrice = $price ?? $averagePrice;
                $newCost = Decimal::costOf($newWeight, $newPrice);
                $number = $firstNumber;
                $rows = [];

                for ($i = 0; $i < $totalBoxes - $count; $i++) {
                    $rows[] = [
                        'barcode' => $this->barcodes->generate($product, $number++, $createdOn),
                        'barcode_source' => BarcodeSource::Generated->value,
                        'manual_barcode_origin' => null,
                        'product_id' => $product->id,
                        'category_id' => $product->category_id,
                        'entry_item_id' => $template->entry_item_id,
                        'origin_chamber_id' => $chamber->id,
                        'initial_weight_kg' => $newWeight,
                        'current_weight_kg' => $newWeight,
                        'price_per_kg_mxn' => $newPrice,
                        'unit_cost_mxn' => $newCost,
                        'entered_at' => $now,
                        'status' => BoxStatus::Available->value,
                        'current_chamber_id' => $chamber->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $this->insertChunked('boxes', $rows);

                foreach (Box::query()->whereIn('barcode', array_column($rows, 'barcode'))->orderBy('id')->get() as $box) {
                    $movements[] = $this->movementRow($box, MovementType::Adjustment, $admin, $now, '0.000', $newWeight, null, BoxStatus::Available, [
                        'to_chamber_id' => $chamber->id,
                        'notes' => "Ajuste de existencias de {$name}: caja agregada. {$reason}",
                    ]);
                }

                $result['added'] = count($rows);
            }

            if ($movements === []) {
                throw new InventoryException('No hay cambios que guardar.');
            }

            $this->insertChunked('box_movements', $movements);

            AuditLog::record($admin, 'stock.adjusted', $chamber, ['product' => $product->name] + $before, [
                'product' => $product->name,
                ...$this->snapshot($this->group($chamber, $product)),
                'reason' => $reason,
                ...$result,
            ]);

            return $result;
        }, 3);
    }

    /**
     * @return Collection<int, Box>
     */
    private function group(Chamber $chamber, Product $product, bool $lock = false)
    {
        return Box::query()
            ->where('current_chamber_id', $chamber->id)
            ->where('product_id', $product->id)
            ->where('status', BoxStatus::Available->value)
            ->orderBy('entered_at')
            ->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();
    }

    /**
     * @param  Collection<int, Box>  $boxes
     * @return array{boxes: int, weight_kg: string, avg_cost_mxn: string, avg_price_per_kg_mxn: string}
     */
    private function snapshot($boxes): array
    {
        return [
            'boxes' => $boxes->count(),
            'weight_kg' => $boxes->reduce(fn (string $sum, Box $box): string => Decimal::add($sum, $box->current_weight_kg, Decimal::WEIGHT_SCALE), '0.000'),
            'avg_cost_mxn' => $this->average($boxes->map(fn (Box $box): string => $box->unit_cost_mxn)->values()->all(), Decimal::MONEY_SCALE),
            'avg_price_per_kg_mxn' => $this->average($boxes->map(fn (Box $box): string => $box->price_per_kg_mxn)->values()->all(), Decimal::MONEY_SCALE),
        ];
    }

    /**
     * @param  array<int, string>  $values
     */
    private function average(array $values, int $scale): string
    {
        if ($values === []) {
            return Decimal::normalize('0', $scale);
        }

        $sum = array_reduce($values, fn (string $carry, string $value): string => Decimal::add($carry, $value, $scale), Decimal::normalize('0', $scale));

        return bcdiv($sum, (string) count($values), $scale);
    }
}
