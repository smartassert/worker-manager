<?php

namespace App\Enum;

enum MachineState: string
{
    case UNKNOWN = 'unknown';
    case FIND_RECEIVED = 'find/received';
    case FIND_FINDING = 'find/finding';
    case FIND_NOT_FOUND = 'find/not-found';
    case FIND_NOT_FINDABLE = 'find/not-findable';
    case CREATE_RECEIVED = 'create/received';
    case CREATE_REQUESTED = 'create/requested';
    case CREATE_FAILED = 'create/failed';
    case UP_STARTED = 'up/started';
    case UP_ACTIVE = 'up/active';
    case DELETE_RECEIVED = 'delete/received';
    case DELETE_REQUESTED = 'delete/requested';
    case DELETE_FAILED = 'delete/failed';
    case DELETE_DELETED = 'delete/deleted';
}
