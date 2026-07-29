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

    private const array END_STATES = [
        self::CREATE_FAILED,
        self::DELETE_FAILED,
        self::DELETE_DELETED,
        self::FIND_NOT_FINDABLE,
        self::FIND_NOT_FOUND,
    ];

    private const array NON_END_STATES = [
        self::UNKNOWN,
        self::FIND_RECEIVED,
        self::FIND_FINDING,
        self::CREATE_RECEIVED,
        self::CREATE_REQUESTED,
        self::UP_STARTED,
        self::UP_ACTIVE,
        self::DELETE_RECEIVED,
        self::DELETE_REQUESTED,
    ];

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

    public function isEnd(): bool
    {
        return in_array($this, self::END_STATES);
    }

    /**
     * @return self[]
     */
    public function getPreviousStates(): array
    {
        if (MachineState::FIND_RECEIVED === $this) {
            return self::NON_END_STATES;
        }

        if (MachineState::FIND_FINDING === $this) {
            return [
                self::FIND_RECEIVED,
            ];
        }

        if (MachineState::CREATE_REQUESTED === $this) {
            return [
                self::CREATE_RECEIVED,
            ];
        }

        if (MachineState::UP_STARTED === $this) {
            return [
                self::FIND_RECEIVED,
                self::FIND_FINDING,
                self::CREATE_RECEIVED,
                self::CREATE_REQUESTED,
            ];
        }

        if (MachineState::UP_ACTIVE === $this) {
            return [
                self::FIND_RECEIVED,
                self::FIND_FINDING,
                self::CREATE_RECEIVED,
                self::CREATE_REQUESTED,
                self::UP_STARTED,
            ];
        }

        if (MachineState::DELETE_RECEIVED === $this) {
            return [
                self::FIND_RECEIVED,
                self::FIND_FINDING,
                self::CREATE_RECEIVED,
                self::CREATE_REQUESTED,
                self::UP_STARTED,
                self::UP_ACTIVE,
            ];
        }

        if (MachineState::DELETE_REQUESTED === $this) {
            return [
                self::FIND_RECEIVED,
                self::FIND_FINDING,
                self::CREATE_RECEIVED,
                self::CREATE_REQUESTED,
                self::UP_STARTED,
                self::UP_ACTIVE,
                self::DELETE_RECEIVED,
            ];
        }

        if ($this->isEnd()) {
            return self::NON_END_STATES;
        }

        return [];
    }
}
