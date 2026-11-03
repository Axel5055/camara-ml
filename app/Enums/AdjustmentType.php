<?php

namespace App\Enums;

enum AdjustmentType: string
{
    case WeightCorrection = 'weight_correction';
    case Found = 'found';
    case Lost = 'lost';
    case Damaged = 'damaged';
    case StatusFix = 'status_fix';

    public function label(): string
    {
        return match ($this) {
            self::WeightCorrection => 'Corrección de peso',
            self::Found => 'Hallazgo',
            self::Lost => 'Pérdida',
            self::Damaged => 'Caja dañada',
            self::StatusFix => 'Corrección de estado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::WeightCorrection => 'sky',
            self::Found => 'green',
            self::Lost => 'red',
            self::Damaged => 'orange',
            self::StatusFix => 'purple',
        };
    }
}
