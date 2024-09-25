<?php

namespace App\Exception\MachineProvider;

use App\Enum\MachineAction;
use App\Enum\MachineProvider;

class AuthenticationException extends Exception implements AuthenticationExceptionInterface
{
    /**
     * @param non-empty-array<\Throwable> $exceptions
     */
    public function __construct(
        private readonly MachineProvider $provider,
        string $machineId,
        MachineAction $action,
        private readonly array $exceptions,
    ) {
        parent::__construct($machineId, $action, $exceptions[0]);
    }

    public function getMachineProvider(): MachineProvider
    {
        return $this->provider;
    }

    /**
     * @return non-empty-array<\Throwable>
     */
    public function getExceptionStack(): array
    {
        return $this->exceptions;
    }
}
