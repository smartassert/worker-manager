<?php

namespace App\Services;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Enum\MachineAction;
use App\Exception\MachineActionFailedException;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\MachineProvider\ProviderMachineNotFoundException;
use App\Exception\MachineProvider\UnknownRemoteMachineExceptionInterface;
use App\Exception\UnsupportedProviderException;
use App\Model\RemoteMachineInterface;
use App\Services\ExceptionFactory\MachineProvider\ExceptionFactory;

readonly class MachineManager
{
    /**
     * @param ProviderMachineManagerInterface[] $providerMachineManagers
     */
    public function __construct(
        private iterable $providerMachineManagers,
        private MachineNameFactory $machineNameFactory,
        private ExceptionFactory $exceptionFactory,
    ) {
    }

    /**
     * @throws MachineActionFailedException
     */
    public function create(Machine $machine): RemoteMachineInterface
    {
        $exceptionStack = [];
        foreach ($this->providerMachineManagers as $machineManager) {
            if ($machineManager instanceof ProviderMachineManagerInterface) {
                try {
                    return $machineManager->create(
                        $machine->getId(),
                        $this->machineNameFactory->create($machine->getId())
                    );
                } catch (\Throwable $exception) {
                    $exceptionStack[] = $this->exceptionFactory->create(
                        $machine->getId(),
                        MachineAction::CREATE,
                        $exception
                    );
                }
            }
        }

        throw new MachineActionFailedException($machine->getId(), MachineAction::CREATE, $exceptionStack);
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

        $provider = $this->findProvider($machineProvider);
        if (null === $provider) {
            throw new UnsupportedProviderException($machineProvider->getName());
        }

        try {
            $machine = $provider->get($machineId, $this->machineNameFactory->create($machineId));
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
     * @param non-empty-string $machineId
     *
     * @throws MachineActionFailedException
     */
    public function remove(string $machineId): void
    {
        $exceptionStack = [];
        foreach ($this->providerMachineManagers as $machineManager) {
            if ($machineManager instanceof ProviderMachineManagerInterface) {
                try {
                    $machineManager->remove($machineId, $this->machineNameFactory->create($machineId));
                } catch (\Throwable $exception) {
                    $newException = $this->exceptionFactory->create($machineId, MachineAction::DELETE, $exception);
                    if (!$newException instanceof UnknownRemoteMachineExceptionInterface) {
                        $exceptionStack[] = $newException;
                    }
                }
            }
        }

        if ([] !== $exceptionStack) {
            throw new MachineActionFailedException($machineId, MachineAction::DELETE, $exceptionStack);
        }
    }

    /**
     * @param non-empty-string $machineId
     *
     * @throws MachineActionFailedException
     */
    public function find(string $machineId): ?RemoteMachineInterface
    {
        $exceptionStack = [];
        foreach ($this->providerMachineManagers as $machineManager) {
            if ($machineManager instanceof ProviderMachineManagerInterface) {
                try {
                    $remoteMachine = $machineManager->get($machineId, $this->machineNameFactory->create($machineId));

                    if ($remoteMachine instanceof RemoteMachineInterface) {
                        return $remoteMachine;
                    }
                } catch (\Throwable $exception) {
                    $exceptionStack[] = $this->exceptionFactory->create($machineId, MachineAction::FIND, $exception);
                }
            }
        }

        if ([] !== $exceptionStack) {
            throw new MachineActionFailedException($machineId, MachineAction::FIND, $exceptionStack);
        }

        return null;
    }

    private function findProvider(MachineProvider $machineProvider): ?ProviderMachineManagerInterface
    {
        $providerName = $machineProvider->getName();

        foreach ($this->providerMachineManagers as $machineManager) {
            if ($machineManager instanceof ProviderMachineManagerInterface) {
                if ($machineManager->getType() === $providerName) {
                    return $machineManager;
                }
            }
        }

        return null;
    }
}
