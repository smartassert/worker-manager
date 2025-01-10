<?php

namespace App\Exception\MachineProvider\DigitalOcean;

use App\Enum\MachineAction;
use App\Exception\MachineProvider\Exception;
use App\Exception\MachineProvider\HttpExceptionInterface;
use App\Services\MachineManager\DigitalOcean\Exception\ErrorException;
use DigitalOceanV2\Exception\RuntimeException;

class HttpException extends Exception implements HttpExceptionInterface
{
    public function __construct(
        string $machineId,
        MachineAction $action,
        ErrorException | RuntimeException $remoteException
    ) {
        parent::__construct($machineId, $action, $remoteException);
    }

    public function getStatusCode(): int
    {
        return $this->getRemoteException()->getCode();
    }
}
