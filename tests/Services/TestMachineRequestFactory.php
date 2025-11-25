<?php

namespace App\Tests\Services;

use App\Message\CheckMachineIsActive;
use App\Message\CreateMachine;
use App\Message\DeleteMachine;
use App\Message\FindMachine;
use App\Message\GetMachine;
use App\Message\MachineRequestInterface;
use App\Services\MachineRequestFactory;

readonly class TestMachineRequestFactory
{
    public function __construct(
        private MachineRequestFactory $factory,
    ) {}

    /**
     * @param non-empty-string $machineId
     */
    public function createFindThenCreate(string $machineId): FindMachine
    {
        return $this->factory->createFindThenCreate($machineId);
    }

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
    public function createFindThenCheckIsActive(string $machineId): FindMachine
    {
        return $this->factory->createFindThenCheckIsActive($machineId);
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

    /**
     * @param non-empty-string $machineId
     */
    public function createCheckIsActive(string $machineId): CheckMachineIsActive
    {
        $factoryReflector = new \ReflectionObject($this->factory);
        $method = $factoryReflector->getMethod('createCheckIsActive');
        $method->setAccessible(true);

        $request = $method->invoke($this->factory, $machineId);
        if (!$request instanceof CheckMachineIsActive) {
            throw new \RuntimeException('Failed to create ' . CheckMachineIsActive::class . ' instance');
        }

        return $request;
    }

    /**
     * @param non-empty-string          $machineId
     * @param MachineRequestInterface[] $onSuccessCollection
     * @param MachineRequestInterface[] $onFailureCollection
     */
    public function createFind(
        string $machineId,
        array $onSuccessCollection = [],
        array $onFailureCollection = []
    ): FindMachine {
        $reflector = new \ReflectionObject($this->factory);
        $method = $reflector->getMethod('createFind');
        $method->setAccessible(true);

        $request = $method->invoke($this->factory, $machineId, $onSuccessCollection, $onFailureCollection);
        if (!$request instanceof FindMachine) {
            throw new \RuntimeException('Failed to create ' . FindMachine::class . ' instance');
        }

        return $request;
    }

    /**
     * @param non-empty-string $machineId
     */
    public function createGet(string $machineId): GetMachine
    {
        $reflector = new \ReflectionObject($this->factory);
        $method = $reflector->getMethod('createGet');
        $method->setAccessible(true);

        $request = $method->invoke($this->factory, $machineId);
        if (!$request instanceof GetMachine) {
            throw new \RuntimeException('Failed to create ' . GetMachine::class . ' instance');
        }

        return $request;
    }
}
