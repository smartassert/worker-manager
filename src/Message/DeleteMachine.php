<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\MachineAction;
use App\Enum\MachineState;

class DeleteMachine extends AbstractRemoteMachineRequest implements MachineActionInterface
{
    public function __construct(string $uniqueId, string $machineId)
    {
        parent::__construct($uniqueId, $machineId);
    }

    public function getAction(): MachineAction
    {
        return MachineAction::DELETE;
    }

    public function getFailureState(): MachineState
    {
        return MachineState::DELETE_FAILED;
    }
}
