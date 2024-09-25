<?php

namespace App\Exception\MachineProvider;

use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Exception\Stack;

class AuthenticationException extends Exception implements AuthenticationExceptionInterface
{
    public function __construct(
        private readonly MachineProvider $provider,
        string $machineId,
        MachineAction $action,
        private readonly Stack $exceptions,
    ) {
        parent::__construct($machineId, $action, $exceptions->first());
    }

    public function getMachineProvider(): MachineProvider
    {
        return $this->provider;
    }

    public function getExceptionStack(): Stack
    {
        return $this->exceptions;
    }
}
