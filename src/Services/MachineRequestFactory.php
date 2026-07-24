<?php

namespace App\Services;

use App\Enum\MachineState;
use App\Message\CreateMachine;
use App\Message\DeleteMachine;
use App\Message\FindMachine;
use App\Message\FindMachineForCreation;
use App\Message\GetMachine;
use App\Message\MachineRequestInterface;

readonly class MachineRequestFactory
{
    public function __construct(
        private RequestIdFactoryInterface $requestIdFactory,
    ) {}

    /**
     * @param non-empty-string $machineId
     */
    public function createFindThenCreate(string $machineId): FindMachine
    {
        return $this->createFind(
            $machineId,
            [],
            [
                $this->createCreate($machineId),
            ]
        )->withOnNotFoundState(MachineState::CREATE_RECEIVED);
    }

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
    public function createFindThenGet(string $machineId): FindMachine
    {
        return $this->createFind(
            $machineId,
            [
                $this->createGetMachine($machineId),
            ]
        );
    }

    /**
     * @param non-empty-string $machineId
     */
    public function createGetMachine(string $machineId): GetMachine
    {
        return new GetMachine($this->requestIdFactory->create(), $machineId);
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
        return new FindMachine(
            $this->requestIdFactory->create(),
            $machineId,
            $onSuccessCollection,
            $onFailureCollection
        );
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
}
