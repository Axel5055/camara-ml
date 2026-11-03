<?php

namespace App\Enums;

enum TransferStatus: string
{
    case Pending = 'pending';
    case InTransit = 'in_transit';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::InTransit => 'En tránsito',
            self::Received => 'Recibida',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::InTransit => 'amber',
            self::Received => 'green',
            self::Cancelled => 'red',
        };
    }
}
