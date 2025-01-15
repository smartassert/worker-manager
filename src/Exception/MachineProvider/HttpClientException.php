<?php

namespace App\Exception\MachineProvider;

use App\Enum\MachineAction;

class HttpClientException extends Exception implements HttpClientExceptionInterface
{
    public function __construct(
        string $machineId,
        MachineAction $action,
        \Throwable $remoteException
    ) {
        parent::__construct($machineId, $action, $remoteException);
    }
}
