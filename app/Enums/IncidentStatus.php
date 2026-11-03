<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
