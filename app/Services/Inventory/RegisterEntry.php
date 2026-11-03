<?php

namespace App\Services\Inventory;

use App\Enums\BarcodeSource;
use App\Enums\BoxStatus;
use App\Enums\DocumentStatus;
use App\Enums\MovementType;
use App\Exceptions\InventoryException;
use App\Models\Box;
use App\Models\Chamber;
use App\Models\Entry;
use App\Models\Product;
use App\Models\User;
use App\Services\Barcodes\BarcodeGenerator;
use App\Services\Barcodes\SequenceAllocator;
use App\Support\Decimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterEntry extends InventoryOperation
{
    public function __construct(
        private SequenceAllocator $sequences,
        private BarcodeGenerator $barcodes,
    ) {}

    /**
     * Register a goods entry. Individual and bulk entries use this same path: a single group of one box,
     * or several groups with different weights and costs.
     *
     * @param  array<int, array{product_id: int, boxes_count: int, weight_per_box_kg: string|int|float, unit_cost_mxn: string|int|float, manual_barcodes?: array<int, string>, manual_barcode_origin?: string|null}>  $groups
     */
    public function handle(
        User $user,
        Chamber $chamber,
        array $groups,
        ?CarbonInterface $occurredAt = null,
        ?string $notes = null,
        ?string $clientToken = null,
    ): Entry {
        $this->authorizeChamber($user, $chamber);

        $clientToken ??= (string) Str::uuid();

        if ($existing = Entry::query()->where('client_token', $clientToken)->first()) {
            return $existing;
        }

        $groups = $this->normalizeGroups($groups);
        $generatedNeeded = array_sum(array_map(fn (array $g): int => $g['boxes_count'] - count($g['manual_barcodes']), $groups));

        // Reserve numbers in short, separate transactions BEFORE the business transaction.
        $firstNumber = $generatedNeeded > 0 ? $this->sequences->next('barcode', $generatedNeeded) : 0;
        $reference = $this->sequences->reference('ENT', $this->sequences->next('entry'));
        $createdOn = now();
        $occurredAt ??= now();

        return DB::transaction(function () use ($user, $chamber, $groups, $firstNumber, $reference, $createdOn, $occurredAt, $notes, $clientToken): Entry {
            $products = Product::query()->with('category')->whereKey(array_column($groups, 'product_id'))->get()->keyBy('id');

            foreach ($groups as $group) {
                $product = $products->get($group['product_id']) ?? throw new InventoryException('El producto seleccionado no existe.');

                if (! $product->is_active || ! $product->category->is_active) {
                    throw new InventoryException("El producto {$product->name} está inactivo.");
                }
            }

            $this->assertManualCodesAreFree($groups);

            $totalBoxes = 0;
            $totalWeight = '0.000';
            $totalCost = '0.00';

            foreach ($groups as $group) {
                $totalBoxes += $group['boxes_count'];
                $totalWeight = Decimal::add($totalWeight, Decimal::mul($group['weight_per_box_kg'], (string) $group['boxes_count'], Decimal::WEIGHT_SCALE), Decimal::WEIGHT_SCALE);
                $totalCost = Decimal::add($totalCost, Decimal::mul($group['unit_cost_mxn'], (string) $group['boxes_count'], Decimal::MONEY_SCALE), Decimal::MONEY_SCALE);
            }

            $entry = Entry::create([
                'reference' => $reference,
                'chamber_id' => $chamber->id,
                'user_id' => $user->id,
                'occurred_at' => $occurredAt,
                'total_boxes' => $totalBoxes,
                'total_weight_kg' => $totalWeight,
                'total_cost_mxn' => $totalCost,
                'notes' => $notes,
                'status' => DocumentStatus::Confirmed,
                'client_token' => $clientToken,
            ]);

            $number = $firstNumber;

            foreach ($groups as $group) {
                $product = $products[$group['product_id']];
                $groupWeight = Decimal::mul($group['weight_per_box_kg'], (string) $group['boxes_count'], Decimal::WEIGHT_SCALE);
                $groupCost = Decimal::mul($group['unit_cost_mxn'], (string) $group['boxes_count'], Decimal::MONEY_SCALE);

                $item = $entry->items()->create([
                    'product_id' => $product->id,
                    'boxes_count' => $group['boxes_count'],
                    'weight_per_box_kg' => $group['weight_per_box_kg'],
                    'unit_cost_mxn' => $group['unit_cost_mxn'],
                    'total_weight_kg' => $groupWeight,
                    'total_cost_mxn' => $groupCost,
                ]);

                $manual = $group['manual_barcodes'];
                $rows = [];

                for ($i = 0; $i < $group['boxes_count']; $i++) {
                    $isManual = isset($manual[$i]);

                    $rows[] = [
                        'barcode' => $isManual ? $manual[$i] : $this->barcodes->generate($product, $number++, $createdOn),
                        'barcode_source' => ($isManual ? BarcodeSource::Manual : BarcodeSource::Generated)->value,
                        'manual_barcode_origin' => $isManual ? $group['manual_barcode_origin'] : null,
                        'product_id' => $product->id,
                        'category_id' => $product->category_id,
                        'entry_item_id' => $item->id,
                        'origin_chamber_id' => $chamber->id,
                        'initial_weight_kg' => $group['weight_per_box_kg'],
                        'current_weight_kg' => $group['weight_per_box_kg'],
                        'unit_cost_mxn' => $group['unit_cost_mxn'],
                        'entered_at' => $occurredAt,
                        'status' => BoxStatus::Available->value,
                        'current_chamber_id' => $chamber->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $this->insertChunked('boxes', $rows);

                $movements = Box::query()->where('entry_item_id', $item->id)->orderBy('id')->pluck('id')
                    ->map(fn (int $boxId): array => $this->movementRow(
                        new Box(['id' => $boxId]),
                        MovementType::Entry,
                        $user,
                        $occurredAt,
                        '0.000',
                        $group['weight_per_box_kg'],
                        null,
                        BoxStatus::Available,
                        ['to_chamber_id' => $chamber->id, 'entry_id' => $entry->id, 'box_id' => $boxId],
                    ))->all();

                $this->insertChunked('box_movements', $movements);
            }

            return $entry->refresh();
        }, 3);
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return list<array{product_id: int, boxes_count: int, weight_per_box_kg: string, unit_cost_mxn: string, manual_barcodes: list<string>, manual_barcode_origin: string|null}>
     */
    private function normalizeGroups(array $groups): array
    {
        if ($groups === []) {
            throw new InventoryException('La entrada necesita al menos un grupo de cajas.');
        }

        $seenManual = [];
        $normalized = [];

        foreach ($groups as $group) {
            $count = (int) ($group['boxes_count'] ?? 0);
            $weight = Decimal::normalize($group['weight_per_box_kg'] ?? '', Decimal::WEIGHT_SCALE);
            $cost = Decimal::normalize($group['unit_cost_mxn'] ?? '', Decimal::MONEY_SCALE);

            if ($count < 1 || $count > 100000) {
                throw new InventoryException('La cantidad de cajas debe estar entre 1 y 100,000.');
            }

            if (Decimal::cmp($weight, '0', Decimal::WEIGHT_SCALE) <= 0) {
                throw new InventoryException('El peso por caja debe ser mayor a cero.');
            }

            if (Decimal::cmp($cost, '0', Decimal::MONEY_SCALE) < 0) {
                throw new InventoryException('El costo unitario no puede ser negativo.');
            }

            $manual = array_map(fn (string $code): string => $this->barcodes->normalizeManual($code), array_values($group['manual_barcodes'] ?? []));
            $origin = isset($group['manual_barcode_origin']) ? trim((string) $group['manual_barcode_origin']) : null;

            if (count($manual) > $count) {
                throw new InventoryException('Hay más códigos manuales que cajas en el grupo.');
            }

            if ($manual !== [] && ($origin === null || $origin === '')) {
                throw new InventoryException('Indica la procedencia de los códigos de barras manuales.');
            }

            foreach ($manual as $code) {
                if (isset($seenManual[$code])) {
                    throw new InventoryException("El código manual {$code} está repetido.");
                }
                $seenManual[$code] = true;
            }

            $normalized[] = [
                'product_id' => (int) ($group['product_id'] ?? 0),
                'boxes_count' => $count,
                'weight_per_box_kg' => $weight,
                'unit_cost_mxn' => $cost,
                'manual_barcodes' => $manual,
                'manual_barcode_origin' => $manual === [] ? null : $origin,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{manual_barcodes: list<string>}>  $groups
     */
    private function assertManualCodesAreFree(array $groups): void
    {
        $codes = array_merge(...array_column($groups, 'manual_barcodes'));

        if ($codes === []) {
            return;
        }

        $taken = Box::query()->whereIn('barcode', $codes)->pluck('barcode');

        if ($taken->isNotEmpty()) {
            throw new InventoryException('Códigos de barras ya registrados: '.$taken->implode(', ').'.');
        }
    }
}
