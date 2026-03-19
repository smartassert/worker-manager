<?php

namespace App\Exception\MachineProvider\DigitalOcean;

use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Exception\MachineProvider\UnprocessableRequestExceptionInterface;

class DropletLimitExceededException extends UnprocessableEntityException
{
    public const string MESSAGE_IDENTIFIER = 'exceed your droplet limit';

    public function __construct(
        string $machineId,
        MachineAction $action,
        \Throwable $remoteException
    ) {
        parent::__construct(
            $machineId,
            $action,
            $remoteException,
            UnprocessableRequestExceptionInterface::CODE_REMOTE_PROVIDER_RESOURCE_LIMIT_REACHED,
            UnprocessableRequestExceptionInterface::REASON_REMOTE_PROVIDER_RESOURCE_LIMIT_REACHED,
        );
    }

    public function getProvider(): MachineProvider
    {
        return MachineProvider::DIGITALOCEAN;
    }
}
