<?php

namespace App\Exception;

class MachineNotRemovableException extends AbstractMachineException implements StackedExceptionInterface
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
        parent::__construct($id);

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
