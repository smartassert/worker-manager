<?php

namespace App\Enum;

enum MachineAction: string
{
    case CREATE = 'create';
    case GET = 'get';
    case DELETE = 'delete';
    case EXISTS = 'exists';
    case FIND = 'find';
    case CHECK_IS_ACTIVE = 'check_is_active';
}
