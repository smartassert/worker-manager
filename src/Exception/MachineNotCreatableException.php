<?php

namespace App\Exception;

class MachineNotCreatableException extends AbstractMachineException implements
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
        array $exceptionStack = [],
    ) {
        parent::__construct($id, sprintf('Machine "%s" is not creatable', $id));

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
