<?php

namespace App\Exception\MachineProvider;

use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Exception\MachineProvider\NotFoundRemoteMachineExceptionInterface as NotFoundRemoteMachineException;

abstract class AbstractNotFoundRemoteMachineException extends Exception implements NotFoundRemoteMachineException
{
    public function __construct(
        private readonly MachineProvider $provider,
        string $machineId,
        MachineAction $action,
        \Throwable $remoteException
    ) {
        parent::__construct($machineId, $action, $remoteException);
    }

    public function getProvider(): MachineProvider
    {
        return $this->provider;
    }
}
