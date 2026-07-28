<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\MachineAction;
use App\Enum\MachineState;

class CreateMachine extends AbstractMachineRequest implements MachineActionInterface
{
    public function getAction(): MachineAction
    {
        return MachineAction::CREATE;
    }

    public function getFailureState(): MachineState
    {
        return MachineState::CREATE_FAILED;
    }
}
