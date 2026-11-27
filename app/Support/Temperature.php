<?php

namespace App\Support;

/**
 * Temperatures in °C with one decimal, handled as strings like the rest of the measurements.
 */
class Temperature
{
    /**
     * The typed text as a one-decimal value ("-18" → "-18.0"), or null when it is not a temperature within the
     * accepted limits. A comma works as the decimal separator.
     */
    public static function parse(string $text): ?string
    {
        $text = str_replace(['−', ','], ['-', '.'], trim($text));

        if (preg_match('/^-?\d{1,2}(\.\d)?$/', $text) !== 1) {
            return null;
        }

        $value = (float) $text;

        if ($value < config('temperature.lowest') || $value > config('temperature.highest')) {
            return null;
        }

        return number_format($value == 0 ? 0 : $value, 1, '.', '');
    }
}
