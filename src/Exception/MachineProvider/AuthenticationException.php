<?php

namespace App\Exception\MachineProvider;

use App\Enum\MachineAction;
use App\Enum\MachineProvider;

class AuthenticationException extends Exception implements AuthenticationExceptionInterface
{
    public function __construct(
        private readonly MachineProvider $provider,
        string $machineId,
        MachineAction $action,
        \Throwable $remoteException
    ) {
        parent::__construct($machineId, $action, $remoteException);
    }

    public function getMachineProvider(): MachineProvider
    {
        return $this->provider;
    }
}
