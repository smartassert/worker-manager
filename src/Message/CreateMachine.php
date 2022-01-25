<?php

declare(strict_types=1);

namespace App\Message;

use App\Model\MachineActionInterface;

class CreateMachine extends AbstractRemoteMachineRequest
{
    public function getAction(): string
    {
        return MachineActionInterface::ACTION_CREATE;
    }
}
