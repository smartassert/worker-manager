<?php

namespace App\Exception\MachineProvider\DigitalOcean;

use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Exception\MachineProvider\Exception;
use App\Exception\MachineProvider\UnprocessableRequestExceptionInterface;
use App\Exception\UnrecoverableExceptionInterface;

class UnprocessableEntityException extends Exception implements
    UnprocessableRequestExceptionInterface,
    UnrecoverableExceptionInterface
{
    private readonly string $reason;

    public function __construct(
        string $machineId,
        MachineAction $action,
        \Throwable $remoteException,
        int $code,
        string $reason,
    ) {
        parent::__construct($machineId, $action, $remoteException, $code);

        $this->reason = $reason;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getProvider(): MachineProvider
    {
        return MachineProvider::DIGITALOCEAN;
    }
}
