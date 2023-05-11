<?php

namespace App\Exception\MachineProvider;

use App\Enum\MachineAction;

class CurlException extends Exception implements CurlExceptionInterface
{
    public function __construct(
        private int $curlCode,
        string $machineId,
        MachineAction $action,
        \Throwable $remoteException
    ) {
        parent::__construct($machineId, $action, $remoteException);
    }

    public function getCurlCode(): int
    {
        return $this->curlCode;
    }
}
