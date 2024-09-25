<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\MachineAction;

class DeleteMachine extends AbstractRemoteMachineRequest implements MachineActionInterface
{
    public function getAction(): MachineAction
    {
        return MachineAction::DELETE;
    }
}
