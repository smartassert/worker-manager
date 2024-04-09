<?php

namespace App\Services;

use App\Enum\MachineProvider;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Model\RemoteMachineInterface;

interface ProviderMachineManagerInterface
{
    /**
     * @return non-empty-string
     */
    public function getType(): string;

    /**
     * @throws ExceptionInterface
     * @throws \Throwable
     */
    public function create(string $machineId, string $name): RemoteMachineInterface;

    /**
     * @throws ExceptionInterface
     * @throws \Throwable
     */
    public function remove(string $machineId, string $name): void;

    /**
     * @throws ExceptionInterface
     * @throws \Throwable
     */
    public function get(string $machineId, string $name): ?RemoteMachineInterface;

    public function supports(MachineProvider $provider): bool;
}
