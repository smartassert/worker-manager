<?php

namespace App\Exception;

use App\Enum\MachineAction;

class MachineNotCreatableException extends AbstractMachineActionFailedException
{
    public function __construct(string $id, array $exceptionStack = [])
    {
        parent::__construct($id, MachineAction::CREATE, $exceptionStack);
    }
}
