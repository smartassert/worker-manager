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

    public const PRE_ACTIVE_STATES = [
        self::CREATE_RECEIVED,
        self::CREATE_REQUESTED,
        self::UP_STARTED,
    ];

    public const END_STATES = [
        self::CREATE_FAILED,
        self::DELETE_FAILED,
        self::DELETE_DELETED,
        self::FIND_NOT_FINDABLE,
        self::FIND_NOT_FOUND,
    ];

    public const RESETTABLE_STATES = [
        self::FIND_NOT_FOUND,
        self::CREATE_FAILED,
    ];

    public const FINDING_STATES = [
        self::FIND_RECEIVED,
        self::FIND_FINDING,
    ];

    public const ENDING_STATES = [
        self::DELETE_RECEIVED,
        self::DELETE_REQUESTED,
    ];

    public const FAILED_STATES = [
        self::CREATE_FAILED,
        self::FIND_NOT_FINDABLE,
        self::FIND_NOT_FOUND,
    ];
}
