<?php

namespace App\Exception;

use App\Enum\MachineAction;

class MachineNotFindableException extends MachineActionFailedException
{
    /**
     * @param non-empty-string $machineId
     */
    public function __construct(string $machineId, array $exceptionStack = [])
    {
        parent::__construct($machineId, MachineAction::FIND, $exceptionStack);
    }
}
