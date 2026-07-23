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

    public static function fromState(MachineState $state): self
    {
        if (in_array(
            $state,
            [
                MachineState::FIND_RECEIVED,
                MachineState::FIND_FINDING,
            ]
        )) {
            return self::FINDING;
        }

        if (in_array(
            $state,
            [
                MachineState::CREATE_RECEIVED,
                MachineState::CREATE_REQUESTED,
                MachineState::UP_STARTED,
            ]
        )) {
            return self::PRE_ACTIVE;
        }

        if (MachineState::UP_ACTIVE === $state) {
            return self::ACTIVE;
        }

        if (in_array(
            $state,
            [
                MachineState::DELETE_RECEIVED,
                MachineState::DELETE_REQUESTED,
            ]
        )) {
            return self::ENDING;
        }

        if (in_array(
            $state,
            [
                MachineState::CREATE_FAILED,
                MachineState::DELETE_FAILED,
                MachineState::DELETE_DELETED,
                MachineState::FIND_NOT_FINDABLE,
                MachineState::FIND_NOT_FOUND,
            ],
        )) {
            return self::END;
        }

        return self::UNKNOWN;
    }
}
