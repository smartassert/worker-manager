<?php

namespace App\Exception;

use App\Enum\MachineAction;

abstract class AbstractMachineActionFailedException extends \Exception implements
    StackedExceptionInterface,
    UnrecoverableExceptionInterface
{
    /**
     * @var \Throwable[]
     */
    private array $exceptionStack;

    /**
     * @param non-empty-string $machineId
     * @param \Throwable[]     $exceptionStack
     */
    public function __construct(string $machineId, MachineAction $action, array $exceptionStack = [])
    {
        parent::__construct(sprintf('Action "%s" on machine "%s" failed', $action->value, $machineId));

        $this->exceptionStack = array_filter($exceptionStack, function ($item) {
            return $item instanceof \Throwable;
        });
    }

    /**
     * @return \Throwable[]
     */
    public function getExceptionStack(): array
    {
        return $this->exceptionStack;
    }
}
