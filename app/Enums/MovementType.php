<?php

namespace App\Enums;

enum MovementType: string
{
    case Entry = 'entry';
    case Exit = 'exit';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case PartialWithdrawal = 'partial_withdrawal';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';
    case TransferCancel = 'transfer_cancel';

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Entrada',
            self::Exit => 'Salida',
            self::TransferOut => 'Envío por transferencia',
            self::TransferIn => 'Recepción por transferencia',
            self::PartialWithdrawal => 'Retiro parcial',
            self::Adjustment => 'Ajuste',
            self::Reversal => 'Reversión',
            self::TransferCancel => 'Transferencia cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Entry => 'green',
            self::Exit => 'red',
            self::TransferOut => 'sky',
            self::TransferIn => 'emerald',
            self::PartialWithdrawal => 'amber',
            self::Adjustment => 'purple',
            self::Reversal => 'orange',
            self::TransferCancel => 'zinc',
        };
    }
}
