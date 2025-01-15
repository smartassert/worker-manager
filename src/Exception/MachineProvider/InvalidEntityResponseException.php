<?php

namespace App\Exception\MachineProvider;

use App\Enum\MachineAction;
use App\Enum\MachineProvider;

class InvalidEntityResponseException extends Exception implements InvalidEntityResponseExceptionInterface
{
    /**
     * @param array<mixed> $data
     */
    public function __construct(
        private readonly MachineProvider $provider,
        private readonly array $data,
        string $machineId,
        MachineAction $action,
        \Throwable $remoteException,
    ) {
        parent::__construct($machineId, $action, $remoteException);
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getMachineProvider(): MachineProvider
    {
        return $this->provider;
    }
}
