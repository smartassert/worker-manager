<?php

namespace App\Exception;

abstract class AbstractMachineException extends \Exception
{
    /**
     * @param non-empty-string $machineId
     */
    public function __construct(
        private string $machineId,
        string $message = '',
        int $code = 0,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return non-empty-string
     */
    public function getMachineId(): string
    {
        return $this->machineId;
    }
}
