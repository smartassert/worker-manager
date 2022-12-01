<?php

namespace App\Exception;

abstract class AbstractMachineException extends \Exception
{
    public function __construct(
        private string $machineId,
        string $message = '',
        int $code = 0,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getMachineId(): string
    {
        return $this->machineId;
    }
}
