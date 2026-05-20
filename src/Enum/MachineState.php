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

    public static function isPreActive(MachineState $state): bool
    {
        return in_array(
            $state,
            [
                self::CREATE_RECEIVED,
                self::CREATE_REQUESTED,
                self::UP_STARTED,
            ]
        );
    }

    public static function isEnd(MachineState $state): bool
    {
        return in_array(
            $state,
            [
                self::CREATE_FAILED,
                self::DELETE_FAILED,
                self::DELETE_DELETED,
                self::FIND_NOT_FINDABLE,
                self::FIND_NOT_FOUND,
            ],
        );
    }

    public static function isResettable(MachineState $state): bool
    {
        return in_array(
            $state,
            [
                self::FIND_NOT_FOUND,
                self::CREATE_FAILED,
            ]
        );
    }

    public static function isFinding(MachineState $state): bool
    {
        return in_array(
            $state,
            [
                self::FIND_RECEIVED,
                self::FIND_FINDING,
            ]
        );
    }

    public static function isEnding(MachineState $state): bool
    {
        return in_array(
            $state,
            [
                self::DELETE_RECEIVED,
                self::DELETE_REQUESTED,
            ]
        );
    }

    public static function isFailed(MachineState $state): bool
    {
        return in_array(
            $state,
            [
                self::CREATE_FAILED,
                self::FIND_NOT_FINDABLE,
                self::FIND_NOT_FOUND,
            ]
        );
    }

    public static function isPending(MachineState $state): bool
    {
        return in_array(
            $state,
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
