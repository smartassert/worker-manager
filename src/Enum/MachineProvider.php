<?php

namespace App\Enum;

enum MachineProvider: string
{
    case DIGITALOCEAN = 'digitalocean';
    case NONE = 'none';
}
