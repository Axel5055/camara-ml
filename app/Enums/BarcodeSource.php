<?php

namespace App\Enums;

enum BarcodeSource: string
{
    case Generated = 'generated';
    case Manual = 'manual';
}
