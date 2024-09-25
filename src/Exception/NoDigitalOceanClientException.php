<?php

namespace App\Exception;

class NoDigitalOceanClientException extends \Exception implements StackedExceptionInterface
{
    /**
     * @param non-empty-array<\Throwable> $exceptionStack
     */
    public function __construct(private readonly array $exceptionStack)
    {
        parent::__construct('', 0);
    }

    /**
     * @return non-empty-array<\Throwable>
     */
    public function getExceptionStack(): array
    {
        return $this->exceptionStack;
    }
}
