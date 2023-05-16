<?php

namespace App\Enum;

enum MachineAction: string
{
    case CREATE = 'create';
    case GET = 'get';
    case DELETE = 'delete';
    case FIND = 'find';
}
