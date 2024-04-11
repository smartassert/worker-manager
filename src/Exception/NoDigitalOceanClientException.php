<?php

namespace App\Exception;

class NoDigitalOceanClientException extends \Exception implements StackedExceptionInterface
{
    /**
     * @var \Throwable[]
     */
    private array $exceptionStack;

    /**
     * @param \Throwable[] $exceptionStack
     */
    public function __construct(array $exceptionStack)
    {
        parent::__construct('', 0);

        foreach ($exceptionStack as $exception) {
            if ($exception instanceof \Throwable) {
                $this->exceptionStack[] = $exception;
            }
        }
    }

    public function getExceptionStack(): array
    {
        return $this->exceptionStack;
    }
}
