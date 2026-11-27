<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum TemperatureShift: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';

    /**
     * The shift the first reading of a day counts as: from 2 pm it is the afternoon one.
     */
    public static function at(CarbonInterface $moment): self
    {
        return $moment->format('H:i') >= config('temperature.afternoon_starts') ? self::Afternoon : self::Morning;
    }

    public function label(): string
    {
        return match ($this) {
            self::Morning => 'Mañana',
            self::Afternoon => 'Tarde',
        };
    }
}
