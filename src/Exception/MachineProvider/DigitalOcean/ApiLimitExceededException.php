<?php

namespace App\Exception\MachineProvider\DigitalOcean;

use App\Enum\MachineAction;
use App\Exception\MachineProvider\ApiLimitExceptionInterface;
use App\Exception\MachineProvider\Exception;

class ApiLimitExceededException extends Exception implements ApiLimitExceptionInterface
{
    public function __construct(
        private int $resetTimestamp,
        string $machineId,
        MachineAction $action,
        \Throwable $remoteException
    ) {
        parent::__construct($machineId, $action, $remoteException);
    }

    public function getResetTimestamp(): int
    {
        return $this->resetTimestamp;
    }
}
