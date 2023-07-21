<?php

namespace App\Services;

use App\Entity\MachineProvider;
use App\Enum\MachineAction;
use App\Exception\MachineNotFindableException;
use App\Exception\MachineNotRemovableException;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\MachineProvider\ProviderMachineNotFoundException;
use App\Exception\MachineProvider\UnknownRemoteMachineExceptionInterface;
use App\Exception\UnsupportedProviderException;
use App\Model\RemoteMachineInterface;
use App\Services\ExceptionFactory\MachineProvider\ExceptionFactory;

class MachineManager extends AbstractMachineManager
{
    /**
     * @param ProviderMachineManagerInterface[] $providerMachineManagers
     */
    public function __construct(
        iterable $providerMachineManagers,
        MachineNameFactory $machineNameFactory,
        private ExceptionFactory $exceptionFactory,
    ) {
        parent::__construct($providerMachineManagers, $machineNameFactory);
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
                : $this->exceptionFactory->create($machineId, MachineAction::CREATE, $exception);
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
                : $this->exceptionFactory->create($machineId, MachineAction::GET, $exception);
        }

        throw new ProviderMachineNotFoundException($machineProvider->getId(), $machineProvider->getName());
    }

    /**
     * @throws MachineNotRemovableException
     * @throws \Throwable
     */
    public function remove(string $machineId): void
    {
        $machineName = $this->createMachineName($machineId);

        $exceptionStack = [];
        foreach ($this->providerMachineManagers as $machineManager) {
            if ($machineManager instanceof ProviderMachineManagerInterface) {
                try {
                    $machineManager->remove($machineId, $machineName);
                } catch (ExceptionInterface $exception) {
                    if (!$exception instanceof UnknownRemoteMachineExceptionInterface) {
                        $exceptionStack[] = $exception;
                    }
                }
            }
        }

        if ([] !== $exceptionStack) {
            throw new MachineNotRemovableException($machineId, $exceptionStack);
        }
    }

    /**
     * @throws MachineNotFindableException
     * @throws \Throwable
     */
    public function find(string $machineId): ?RemoteMachineInterface
    {
        $machineName = $this->createMachineName($machineId);

        $exceptionStack = [];
        foreach ($this->providerMachineManagers as $machineManager) {
            if ($machineManager instanceof ProviderMachineManagerInterface) {
                try {
                    $remoteMachine = $machineManager->get($machineId, $machineName);

                    if ($remoteMachine instanceof RemoteMachineInterface) {
                        return $remoteMachine;
                    }
                } catch (ExceptionInterface $exception) {
                    $exceptionStack[] = $exception;
                }
            }
        }

        if ([] !== $exceptionStack) {
            throw new MachineNotFindableException($machineId, $exceptionStack);
        }

        return null;
    }
}
