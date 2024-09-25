<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\MachineAction;
use App\Enum\MachineState;

class GetMachine extends AbstractRemoteMachineRequest implements MachineActionInterface
{
    public function getAction(): MachineAction
    {
        return MachineAction::GET;
    }

    public function getFailureState(): MachineState
    {
        return MachineState::FIND_NOT_FOUND;
    }
}
