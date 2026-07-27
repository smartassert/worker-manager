<?php

namespace App\Tests\Services;

use App\Message\CreateMachine;
use App\Message\DeleteMachine;
use App\Services\MachineRequestFactory;

readonly class TestMachineRequestFactory
{
    public function __construct(
        private MachineRequestFactory $factory,
    ) {}

    /**
     * @param non-empty-string $machineId
     */
    public function createDelete(string $machineId): DeleteMachine
    {
        return $this->factory->createDelete($machineId);
    }

    /**
     * @param non-empty-string $machineId
     */
    public function createCreate(string $machineId): CreateMachine
    {
        $reflector = new \ReflectionObject($this->factory);
        $method = $reflector->getMethod('createCreate');
        $method->setAccessible(true);

        $request = $method->invoke($this->factory, $machineId);
        if (!$request instanceof CreateMachine) {
            throw new \RuntimeException('Failed to create ' . CreateMachine::class . ' instance');
        }

        return $request;
    }
}
