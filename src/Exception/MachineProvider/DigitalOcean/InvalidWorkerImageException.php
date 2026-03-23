<?php

namespace App\Exception\MachineProvider\DigitalOcean;

use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Exception\MachineProvider\InvalidProviderImageExceptionInterface;
use App\Exception\MachineProvider\UnprocessableRequestExceptionInterface;
use App\Services\MachineManager\DigitalOcean\Exception\ErrorException;

class InvalidWorkerImageException extends UnprocessableEntityException implements InvalidProviderImageExceptionInterface
{
    public function __construct(
        string $machineId,
        MachineAction $action,
        ErrorException $remoteException,
        private readonly string $image,
    ) {
        parent::__construct(
            $machineId,
            $action,
            $remoteException,
            UnprocessableRequestExceptionInterface::CODE_PROVIDER_IMAGE_INVALID,
            UnprocessableRequestExceptionInterface::REASON_PROVIDER_IMAGE_INVALID,
        );
    }

    public function getProvider(): MachineProvider
    {
        return MachineProvider::DIGITALOCEAN;
    }

    public function getImage(): string
    {
        return $this->image;
    }
}
