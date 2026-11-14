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
use Illuminate\Support\Facades\DB;

class AddBoxesToChamber extends InventoryOperation
{
    public function __construct(private SequenceAllocator $sequences, private BarcodeGenerator $barcodes) {}

    /**
     * Add boxes of any product straight to a chamber, without an entry (super administrators only). Each box is
     * created available with its own barcode, and the movement history and the audit log record who did it and why.
     *
     * @return int number of boxes created
     */
    public function handle(
        User $admin,
        Chamber $chamber,
        Product $product,
        int $boxes,
        string|int|float $weightPerBox,
        string|int|float $pricePerKg,
        string $reason,
    ): int {
        if (! $admin->isSuperAdmin()) {
            throw new AuthorizationException('Solo el super administrador puede agregar cajas sin entrada.');
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw new InventoryException('Indica el motivo (mínimo 5 caracteres).');
        }

        if ($boxes < 1 || $boxes > 10000) {
            throw new InventoryException('La cantidad de cajas debe estar entre 1 y 10,000.');
        }

        if (! $chamber->is_active) {
            throw new InventoryException("La cámara {$chamber->name} está inactiva.");
        }

        $weight = Decimal::normalize($weightPerBox, Decimal::WEIGHT_SCALE);
        $price = Decimal::normalize($pricePerKg, Decimal::MONEY_SCALE);

        if (Decimal::cmp($weight, '0', Decimal::WEIGHT_SCALE) <= 0) {
            throw new InventoryException('El peso por caja debe ser mayor a cero.');
        }

        if (Decimal::cmp($price, '0', Decimal::MONEY_SCALE) < 0) {
            throw new InventoryException('El costo por kilo no puede ser negativo.');
        }

        $firstNumber = $this->sequences->next('barcode', $boxes);
        $now = now();
        $cost = Decimal::costOf($weight, $price);

        return DB::transaction(function () use ($admin, $chamber, $product, $boxes, $weight, $price, $cost, $reason, $firstNumber, $now): int {
            $number = $firstNumber;
            $rows = [];

            for ($i = 0; $i < $boxes; $i++) {
                $rows[] = [
                    'barcode' => $this->barcodes->generate($product, $number++, $now),
                    'barcode_source' => BarcodeSource::Generated->value,
                    'manual_barcode_origin' => null,
                    'product_id' => $product->id,
                    'category_id' => $product->category_id,
                    'entry_item_id' => null,
                    'origin_chamber_id' => $chamber->id,
                    'initial_weight_kg' => $weight,
                    'current_weight_kg' => $weight,
                    'price_per_kg_mxn' => $price,
                    'unit_cost_mxn' => $cost,
                    'entered_at' => $now,
                    'status' => BoxStatus::Available->value,
                    'current_chamber_id' => $chamber->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $this->insertChunked('boxes', $rows);

            $movements = [];

            foreach (Box::query()->whereIn('barcode', array_column($rows, 'barcode'))->orderBy('id')->get() as $box) {
                $movements[] = $this->movementRow($box, MovementType::Adjustment, $admin, $now, '0.000', $weight, null, BoxStatus::Available, [
                    'to_chamber_id' => $chamber->id,
                    'notes' => "Caja agregada sin entrada de {$product->name}. {$reason}",
                ]);
            }

            $this->insertChunked('box_movements', $movements);

            AuditLog::record($admin, 'stock.boxes_added', $chamber, null, [
                'product' => $product->name,
                'boxes' => $boxes,
                'weight_per_box_kg' => $weight,
                'price_per_kg_mxn' => $price,
                'reason' => $reason,
            ]);

            return $boxes;
        }, 3);
    }
}
