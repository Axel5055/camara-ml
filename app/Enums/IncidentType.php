<?php

namespace App\Enums;

enum IncidentType: string
{
    case Missing = 'missing';
    case Unexpected = 'unexpected';
    case WeightDifference = 'weight_difference';
    case Damaged = 'damaged';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Missing => 'Faltante',
            self::Unexpected => 'Inesperada',
            self::WeightDifference => 'Diferencia de peso',
            self::Damaged => 'Dañada',
            self::Other => 'Otra',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Missing => 'red',
            self::Unexpected => 'purple',
            self::WeightDifference => 'amber',
            self::Damaged => 'orange',
            self::Other => 'zinc',
        };
    }
}
