<?php

namespace App\Services\ExceptionFactory\MachineProvider;

use App\Enum\MachineAction;
use App\Exception\MachineProvider\ExceptionInterface;

interface ExceptionFactoryInterface
{
    public function handles(\Throwable $exception): bool;

    /**
     * @param non-empty-string $resourceId
     */
    public function create(string $resourceId, MachineAction $action, \Throwable $exception): ?ExceptionInterface;
}
