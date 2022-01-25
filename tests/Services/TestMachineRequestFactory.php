<?php

namespace App\Tests\Services;

use App\Message\CheckMachineIsActive;
use App\Message\CreateMachine;
use App\Message\DeleteMachine;
use App\Message\FindMachine;
use App\Message\GetMachine;
use App\Message\MachineRequestInterface;
use App\Services\MachineRequestFactory;
use Symfony\Component\Messenger\Stamp\StampInterface;

class TestMachineRequestFactory
{
    public function __construct(
        private MachineRequestFactory $factory,
    ) {
    }

    public function createFindThenCreate(string $machineId): FindMachine
    {
        return $this->factory->createFindThenCreate($machineId);
    }

    public function createDelete(string $machineId): DeleteMachine
    {
        return $this->factory->createDelete($machineId);
    }

    public function createFindThenCheckIsActive(string $machineId): FindMachine
    {
        return $this->factory->createFindThenCheckIsActive($machineId);
    }

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
     * @param StampInterface[] $stamps
     */
    public function createCheckIsActive(string $machineId, array $stamps = []): CheckMachineIsActive
    {
        $factoryReflector = new \ReflectionObject($this->factory);
        $method = $factoryReflector->getMethod('createCheckIsActive');
        $method->setAccessible(true);

        $request = $method->invoke($this->factory, $machineId);
        if (!$request instanceof CheckMachineIsActive) {
            throw new \RuntimeException('Failed to create ' . CheckMachineIsActive::class . ' instance');
        }

        $requestReflector = new \ReflectionClass($request);
        $property = $requestReflector->getProperty('stamps');
        $property->setAccessible(true);
        $property->setValue($request, $stamps);

        return $request;
    }

    /**
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
