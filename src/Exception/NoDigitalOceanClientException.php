<?php

namespace App\Exception;

class NoDigitalOceanClientException extends \Exception implements StackedExceptionInterface
{
    public function __construct(private readonly Stack $exceptionStack)
    {
        parent::__construct('', 0);
    }

    public function getExceptionStack(): Stack
    {
        return $this->exceptionStack;
    }
}
