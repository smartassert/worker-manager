<?php

namespace App\Exception;

use App\Enum\MachineAction;

class MachineActionFailedException extends \Exception implements
    StackedExceptionInterface,
    UnrecoverableExceptionInterface
{
    /**
     * @param non-empty-string            $machineId
     * @param non-empty-array<\Throwable> $exceptionStack
     */
    public function __construct(
        string $machineId,
        private readonly MachineAction $action,
        private readonly array $exceptionStack
    ) {
        parent::__construct(sprintf('Action "%s" on machine "%s" failed', $action->value, $machineId));
    }

    public function getAction(): MachineAction
    {
        return $this->action;
    }

    /**
     * @return non-empty-array<\Throwable>
     */
    public function getExceptionStack(): array
    {
        return $this->exceptionStack;
    }
}
