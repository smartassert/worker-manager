<?php

namespace App\Exception;

use App\Enum\MachineAction;

class MachineActionFailedException extends \Exception implements
    StackedExceptionInterface,
    UnrecoverableExceptionInterface
{
    /**
     * @param non-empty-string $machineId
     */
    public function __construct(
        string $machineId,
        private readonly MachineAction $action,
        private readonly Stack $exceptionStack
    ) {
        parent::__construct(sprintf('Action "%s" on machine "%s" failed', $action->value, $machineId));
    }

    public function getAction(): MachineAction
    {
        return $this->action;
    }

    public function getExceptionStack(): Stack
    {
        return $this->exceptionStack;
    }
}
