<?php

namespace App\Enums;

enum BoxStatus: string
{
    case Available = 'available';
    case InTransit = 'in_transit';
    case Dispatched = 'dispatched';
    case Depleted = 'depleted';
    case WrittenOff = 'written_off';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::InTransit => 'En tránsito',
            self::Dispatched => 'Entregada',
            self::Depleted => 'Agotada',
            self::WrittenOff => 'Baja',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'green',
            self::InTransit => 'amber',
            self::Dispatched => 'blue',
            self::Depleted => 'zinc',
            self::WrittenOff => 'red',
        };
    }
}
