<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Confirmed = 'confirmed';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmada',
            self::Reversed => 'Revertida',
        };
    }
}
