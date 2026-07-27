<?php

namespace App\Services;

use App\Message\CreateMachine;
use App\Message\DeleteMachine;
use App\Message\FindMachineAfterDeletion;
use App\Message\FindMachineForCreation;
use App\Message\FindMachineForRetrieval;
use App\Message\GetMachine;

readonly class MachineRequestFactory
{
    public function __construct(
        private RequestIdFactoryInterface $requestIdFactory,
    ) {}

    /**
     * @param non-empty-string $machineId
     */
    public function createDelete(string $machineId): DeleteMachine
    {
        return new DeleteMachine($this->requestIdFactory->create(), $machineId);
    }

    /**
     * @param non-empty-string $machineId
     */
    public function createGetMachine(string $machineId): GetMachine
    {
        return new GetMachine($this->requestIdFactory->create(), $machineId);
    }

    /**
     * @param non-empty-string $machineId
     */
    public function createCreate(string $machineId): CreateMachine
    {
        return new CreateMachine($this->requestIdFactory->create(), $machineId);
    }

    /**
     * @param non-empty-string $machineId
     */
    public function createFindMachineForCreation(string $machineId): FindMachineForCreation
    {
        return new FindMachineForCreation($this->requestIdFactory->create(), $machineId);
    }

    /**
     * @param non-empty-string $machineId
     */
    public function createFindMachineForRetrieval(string $machineId): FindMachineForRetrieval
    {
        return new FindMachineForRetrieval($this->requestIdFactory->create(), $machineId);
    }

    /**
     * @param non-empty-string $machineId
     */
    public function createFindMachineAfterDeletion(string $machineId): FindMachineAfterDeletion
    {
        return new FindMachineAfterDeletion($this->requestIdFactory->create(), $machineId);
    }
}
