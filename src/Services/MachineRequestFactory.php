<?php

namespace App\Services;

use App\Enum\MachineState;
use App\Message\CheckMachineIsActive;
use App\Message\CreateMachine;
use App\Message\DeleteMachine;
use App\Message\FindMachine;
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
        $findRequest = $this
            ->createFind($machineId)
            ->withOnNotFoundState(MachineState::DELETE_DELETED)
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

    /**
     * @param non-empty-string $machineId
     */
    public function createFindThenCheckIsActive(string $machineId): FindMachine
    {
        return $this->createFind(
            $machineId,
            [
                $this->createCheckIsActive($machineId),
            ]
        );
    }

    /**
     * @param non-empty-string $machineId
     */
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

    /**
     * @param non-empty-string $machineId
     */
    private function createGet(string $machineId): GetMachine
    {
        return new GetMachine($this->requestIdFactory->create(), $machineId);
    }

    /**
     * @param non-empty-string          $machineId
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

    /**
     * @param non-empty-string $machineId
     */
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
