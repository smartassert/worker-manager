<?php

namespace App\Services;

use App\Entity\MachineProvider;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\MachineProvider\ProviderMachineNotFoundException;
use App\Exception\UnsupportedProviderException;
use App\Model\MachineActionInterface as Action;
use App\Model\RemoteMachineInterface;
use App\Services\ExceptionFactory\MachineProvider\ExceptionFactory;

class MachineManager extends AbstractMachineManager
{
    public function __construct(
        MachineManagerStack $machineManagerStack,
        MachineNameFactory $machineNameFactory,
        private ExceptionFactory $exceptionFactory,
    ) {
        parent::__construct($machineManagerStack, $machineNameFactory);
    }

    /**
     * @throws ExceptionInterface
     * @throws UnsupportedProviderException
     * @throws \Throwable
     */
    public function create(MachineProvider $machineProvider): RemoteMachineInterface
    {
        $machineId = $machineProvider->getId();
        $machineName = $this->createMachineName($machineId);

        $provider = $this->findProvider($machineProvider);
        if (null === $provider) {
            throw new UnsupportedProviderException($machineProvider->getName());
        }

        try {
            return $provider->create($machineId, $machineName);
        } catch (\Throwable $exception) {
            throw $exception instanceof ExceptionInterface
                ? $exception
                : $this->exceptionFactory->create($machineId, Action::ACTION_CREATE, $exception);
        }
    }

    /**
     * @throws ExceptionInterface
     * @throws UnsupportedProviderException
     * @throws ProviderMachineNotFoundException
     * @throws \Throwable
     */
    public function get(MachineProvider $machineProvider): RemoteMachineInterface
    {
        $machineId = $machineProvider->getId();
        $machineName = $this->createMachineName($machineId);

        $provider = $this->findProvider($machineProvider);
        if (null === $provider) {
            throw new UnsupportedProviderException($machineProvider->getName());
        }

        try {
            $machine = $provider->get($machineId, $machineName);
            if ($machine instanceof RemoteMachineInterface) {
                return $machine;
            }
        } catch (\Throwable $exception) {
            throw $exception instanceof ExceptionInterface
                ? $exception
                : $this->exceptionFactory->create($machineId, Action::ACTION_GET, $exception);
        }

        throw new ProviderMachineNotFoundException($machineProvider->getId(), $machineProvider->getName());
    }

    /**
     * @throws ExceptionInterface
     * @throws UnsupportedProviderException
     * @throws \Throwable
     */
    public function delete(MachineProvider $machineProvider): void
    {
        $machineId = $machineProvider->getId();
        $machineName = $this->createMachineName($machineId);

        $provider = $this->findProvider($machineProvider);
        if (null === $provider) {
            throw new UnsupportedProviderException($machineProvider->getName());
        }

        try {
            $provider->remove($machineId, $machineName);
        } catch (\Throwable $exception) {
            throw $exception instanceof ExceptionInterface
                ? $exception
                : $this->exceptionFactory->create($machineId, Action::ACTION_DELETE, $exception);
        }
    }
}
