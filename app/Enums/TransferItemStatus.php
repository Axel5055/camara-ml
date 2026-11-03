<?php

namespace App\Enums;

enum TransferItemStatus: string
{
    case Expected = 'expected';
    case Received = 'received';
    case Missing = 'missing';
}
