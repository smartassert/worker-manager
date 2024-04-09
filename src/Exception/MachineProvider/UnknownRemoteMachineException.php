<?php

namespace App\Exception\MachineProvider;

use App\Enum\MachineAction;
use App\Enum\MachineProvider;

class UnknownRemoteMachineException extends Exception implements UnknownRemoteMachineExceptionInterface
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

    public function isRecoverable(): bool
    {
        return MachineAction::GET !== $this->getAction();
    }
}
