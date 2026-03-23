<?php

namespace App\Exception\MachineProvider\DigitalOcean;

use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Exception\MachineProvider\Exception;
use App\Exception\MachineProvider\UnprocessableRequestExceptionInterface;
use App\Exception\UnrecoverableExceptionInterface;
use App\Services\MachineManager\DigitalOcean\Exception\ErrorException;

class UnprocessableEntityException extends Exception implements
    UnprocessableRequestExceptionInterface,
    UnrecoverableExceptionInterface
{
    private readonly string $reason;

    public function __construct(
        string $machineId,
        MachineAction $action,
        ErrorException $remoteException,
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
