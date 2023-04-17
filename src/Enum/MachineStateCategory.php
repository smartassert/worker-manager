<?php

namespace App\Enum;

enum MachineStateCategory: string
{
    case UNKNOWN = 'unknown';
    case FINDING = 'finding';
    case PRE_ACTIVE = 'pre_active';
    case ACTIVE = 'active';
    case ENDING = 'ending';
    case END = 'end';
}
