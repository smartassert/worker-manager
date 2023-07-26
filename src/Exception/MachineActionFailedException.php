<?php

namespace App\Exception;

use App\Enum\MachineAction;

abstract class MachineActionFailedException extends AbstractMachineException implements
    StackedExceptionInterface,
    UnrecoverableExceptionInterface
{
    /**
     * @var \Throwable[]
     */
    private array $exceptionStack;

    /**
     * @param \Throwable[] $exceptionStack
     */
    public function __construct(
        string $id,
        MachineAction $action,
        array $exceptionStack = [],
    ) {
        parent::__construct($id, sprintf('Action "%s" on machine "%s" failed', $action->value, $id));

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
