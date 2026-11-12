<?php

namespace App\Support;

use App\Exceptions\InventoryException;

/**
 * Fixed-point arithmetic on numeric strings (kilograms use scale 3, MXN uses scale 2). Never floats.
 */
final class Decimal
{
    public const WEIGHT_SCALE = 3;

    public const MONEY_SCALE = 2;

    /**
     * Validate and normalize a numeric string to exactly $scale decimals, rejecting extra precision.
     */
    public static function normalize(string|int|float $value, int $scale): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InventoryException("El valor '{$value}' no es un número válido.");
        }

        $decimals = str_contains($value, '.') ? strlen(substr($value, strpos($value, '.') + 1)) : 0;

        if ($decimals > $scale && rtrim(substr($value, strpos($value, '.') + 1 + $scale), '0') !== '') {
            throw new InventoryException("El valor '{$value}' admite máximo {$scale} decimales.");
        }

        return bcadd(self::numeric($value), '0', $scale);
    }

    public static function add(string $a, string $b, int $scale): string
    {
        return bcadd(self::numeric($a), self::numeric($b), $scale);
    }

    public static function sub(string $a, string $b, int $scale): string
    {
        return bcsub(self::numeric($a), self::numeric($b), $scale);
    }

    public static function mul(string $a, string $b, int $scale): string
    {
        return bcmul(self::numeric($a), self::numeric($b), $scale);
    }

    public static function cmp(string $a, string $b, int $scale = 6): int
    {
        return bccomp(self::numeric($a), self::numeric($b), $scale);
    }

    /**
     * Cost of a quantity of goods priced per kilo: $weightKg × $pricePerKg, rounded half-up to cents.
     */
    public static function costOf(string $weightKg, string $pricePerKg): string
    {
        return self::share($pricePerKg, $weightKg, '1', self::MONEY_SCALE);
    }

    /**
     * Proportional share: $amount × $part / $whole, rounded half-up to $scale.
     */
    public static function share(string $amount, string $part, string $whole, int $scale): string
    {
        $raw = bcdiv(bcmul(self::numeric($amount), self::numeric($part), 9), self::numeric($whole), 9);
        $half = '0.'.str_repeat('0', $scale).'5';

        return bcadd($raw, self::numeric($half), $scale);
    }

    /**
     * @return numeric-string
     */
    private static function numeric(string $value): string
    {
        if (! is_numeric($value)) {
            throw new InventoryException("El valor '{$value}' no es un número válido.");
        }

        return $value;
    }
}
