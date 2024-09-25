<?php

namespace App\Exception;

/**
 * @implements \IteratorAggregate<\Throwable>
 */
readonly class Stack implements \IteratorAggregate
{
    /**
     * @param non-empty-array<\Throwable> $exceptions
     */
    public function __construct(
        private array $exceptions,
    ) {
    }

    /**
     * @return \Throwable[]
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    public function first(): \Throwable
    {
        return $this->exceptions[0];
    }

    /**
     * @return \Traversable<\Throwable>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->exceptions);
    }
}
