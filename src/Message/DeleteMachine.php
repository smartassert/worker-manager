<?php

declare(strict_types=1);

namespace App\Message;

use App\Model\MachineActionInterface;

class DeleteMachine extends AbstractRemoteMachineRequest
{
    public function getAction(): string
    {
        return MachineActionInterface::ACTION_DELETE;
    }
}
