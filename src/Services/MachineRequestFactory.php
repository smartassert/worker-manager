<?php

namespace App\Services;

use App\Entity\Machine;
use App\Message\CheckMachineIsActive;
use App\Message\CreateMachine;
use App\Message\DeleteMachine;
use App\Message\FindMachine;
use App\Message\GetMachine;
use App\Message\MachineRequestInterface;

class MachineRequestFactory
{
    public function __construct(
        private RequestIdFactoryInterface $requestIdFactory
    ) {
    }

    public function createFindThenCreate(string $machineId): FindMachine
    {
        return $this->createFind(
            $machineId,
            [],
            [
                $this->createCreate($machineId),
            ]
        );
    }

    public function createDelete(string $machineId): DeleteMachine
    {
        $findRequest = $this
            ->createFind($machineId)
            ->withOnNotFoundState(Machine::STATE_DELETE_DELETED)
            ->withReDispatchOnSuccess(true)
        ;

        return new DeleteMachine(
            $this->requestIdFactory->create(),
            $machineId,
            [
                $findRequest,
            ]
        );
    }

    public function createFindThenCheckIsActive(string $machineId): FindMachine
    {
        return $this->createFind(
            $machineId,
            [
                $this->createCheckIsActive($machineId),
            ]
        );
    }

    private function createCheckIsActive(string $machineId): CheckMachineIsActive
    {
        return new CheckMachineIsActive(
            $this->requestIdFactory->create(),
            $machineId,
            [
                $this->createGet($machineId),
            ]
        );
    }

    private function createGet(string $machineId): GetMachine
    {
        return new GetMachine($this->requestIdFactory->create(), $machineId);
    }

    /**
     * @param MachineRequestInterface[] $onSuccessCollection
     * @param MachineRequestInterface[] $onFailureCollection
     */
    private function createFind(
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

    private function createCreate(string $machineId): CreateMachine
    {
        return new CreateMachine(
            $this->requestIdFactory->create(),
            $machineId,
            [
                $this->createCheckIsActive($machineId),
            ]
        );
    }
}
