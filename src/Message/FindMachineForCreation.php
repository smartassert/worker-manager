<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\MachineAction;
use App\Enum\MachineState;

class FindMachineForCreation extends AbstractRemoteMachineRequest implements MachineActionInterface
{
    public function getAction(): MachineAction
    {
        return MachineAction::FIND;
    }

    public function getFailureState(): MachineState
    {
        return MachineState::FIND_NOT_FINDABLE;
    }
}
