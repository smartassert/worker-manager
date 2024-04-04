<?php

namespace App\Exception\MachineProvider\DigitalOcean;

use App\Enum\MachineAction;
use App\Exception\MachineProvider\Exception;
use App\Exception\MachineProvider\UnprocessableRequestExceptionInterface;
use App\Exception\UnrecoverableExceptionInterface;

class DropletLimitExceededException extends Exception implements
    UnprocessableRequestExceptionInterface,
    UnrecoverableExceptionInterface
{
    public const MESSAGE_IDENTIFIER = 'exceed your droplet limit';

    public function __construct(
        string $machineId,
        MachineAction $action,
        \Throwable $remoteException
    ) {
        parent::__construct(
            $machineId,
            $action,
            $remoteException,
            UnprocessableRequestExceptionInterface::CODE_REMOTE_PROVIDER_RESOURCE_LIMIT_REACHED
        );
    }

    /**
     * @return UnprocessableRequestExceptionInterface::REASON_REMOTE_PROVIDER_RESOURCE_LIMIT_REACHED
     */
    public function getReason(): string
    {
        return UnprocessableRequestExceptionInterface::REASON_REMOTE_PROVIDER_RESOURCE_LIMIT_REACHED;
    }
}
