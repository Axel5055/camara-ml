<?php

namespace App\Services\Barcodes;

use App\Exceptions\InventoryException;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

class BarcodeGenerator
{
    /** Shape of every generated code: PRODUCT-YYYYMMDD-000001 (the counter grows past 6 digits when needed). */
    public const GENERATED_PATTERN = '/^[A-Z0-9]+-\d{8}-\d{6,}$/';

    /** Allowed shape for manually registered codes. */
    public const MANUAL_PATTERN = '/^[A-Z0-9][A-Z0-9._-]{1,49}$/';

    public function generate(Product $product, int $number, ?CarbonInterface $date = null): string
    {
        return $this->format($product->code, $number, $date);
    }

    public function format(string $productCode, int $number, ?CarbonInterface $date = null): string
    {
        return sprintf('%s-%s-%06d', $productCode, ($date ?? Date::now())->format('Ymd'), $number);
    }

    /**
     * Normalize a pre-printed label and make sure it can never collide with a future generated code.
     */
    public function normalizeManual(string $code): string
    {
        $code = strtoupper(trim($code));

        if (! preg_match(self::MANUAL_PATTERN, $code)) {
            throw new InventoryException("El código manual '{$code}' no es válido (2 a 50 caracteres: A-Z, 0-9, punto, guion).");
        }

        if (preg_match(self::GENERATED_PATTERN, $code)) {
            throw new InventoryException("El código manual '{$code}' usa el formato reservado para códigos generados por el sistema.");
        }

        return $code;
    }
}
