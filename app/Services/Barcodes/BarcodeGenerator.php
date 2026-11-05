<?php

namespace App\Services\Barcodes;

use App\Exceptions\InventoryException;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

class BarcodeGenerator
{
    /**
     * Shape of every generated code: AAA##BBB##CCC000000, where AAA, BBB and CCC are the first nine letters
     * of the product name, the first ## is the day and the second ## the month of the entry, and the counter
     * grows past 6 digits when needed. Example: TILAPIA MC, entered on 5 November, is TIL05API11AMC000001.
     */
    public const GENERATED_PATTERN = '/^[A-Z]{3}\d{2}[A-Z]{3}\d{2}[A-Z]{3}\d{6,}$/';

    /** Shape the system used before: PRODUCT-YYYYMMDD-000001. Those boxes keep their code, and manual labels may not use it. */
    public const LEGACY_PATTERN = '/^[A-Z0-9]+-\d{8}-\d{6,}$/';

    /** Allowed shape for manually registered codes. */
    public const MANUAL_PATTERN = '/^[A-Z0-9][A-Z0-9._-]{1,49}$/';

    public function generate(Product $product, int $number, ?CarbonInterface $date = null): string
    {
        return $this->format($product->name, $number, $date);
    }

    public function format(string $productName, int $number, ?CarbonInterface $date = null): string
    {
        $date ??= Date::now();
        $letters = str_pad(substr(preg_replace('/[^A-Z]/', '', strtoupper(Str::ascii($productName))), 0, 9), 9, 'X');

        return sprintf(
            '%s%s%s%s%s%06d',
            substr($letters, 0, 3),
            $date->format('d'),
            substr($letters, 3, 3),
            $date->format('m'),
            substr($letters, 6, 3),
            $number,
        );
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

        if (preg_match(self::GENERATED_PATTERN, $code) || preg_match(self::LEGACY_PATTERN, $code)) {
            throw new InventoryException("El código manual '{$code}' usa el formato reservado para códigos generados por el sistema.");
        }

        return $code;
    }
}
