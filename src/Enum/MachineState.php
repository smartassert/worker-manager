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

    public function isResettable(): bool
    {
        return in_array(
            $this,
            [
                self::FIND_NOT_FOUND,
                self::CREATE_FAILED,
            ]
        );
    }

    public function isFinding(): bool
    {
        return in_array(
            $this,
            [
                self::FIND_RECEIVED,
                self::FIND_FINDING,
            ]
        );
    }

    public function isEnding(): bool
    {
        return in_array(
            $this,
            [
                self::DELETE_RECEIVED,
                self::DELETE_REQUESTED,
            ]
        );
    }

    public function isFailed(): bool
    {
        return in_array(
            $this,
            [
                self::CREATE_FAILED,
                self::FIND_NOT_FINDABLE,
                self::FIND_NOT_FOUND,
            ]
        );
    }

    public function isPending(): bool
    {
        return in_array(
            $this,
            [
                self::UNKNOWN,
                self::FIND_RECEIVED,
                self::FIND_FINDING,
                self::CREATE_RECEIVED,
                self::CREATE_REQUESTED,
            ]
        );
    }
}
