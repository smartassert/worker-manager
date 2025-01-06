<?php

namespace App\Services\MachineManager;

use App\Entity\Machine;
use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Exception\MachineActionFailedException;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\MachineProvider\NotFoundRemoteMachineExceptionInterface;
use App\Exception\MachineProvider\ProviderMachineNotFoundException;
use App\Exception\Stack;
use App\Exception\UnsupportedProviderException;
use App\Model\RemoteMachineInterface;
use App\Services\ExceptionFactory\MachineProvider\ExceptionFactory;
use App\Services\ExceptionIdentifier\ExceptionIdentifier;
use App\Services\MachineNameFactory;

readonly class MachineManager
{
    /**
     * @param non-empty-array<ProviderMachineManagerInterface> $providerMachineManagers
     */
    public function __construct(
        private array $providerMachineManagers,
        private MachineNameFactory $machineNameFactory,
        private ExceptionFactory $exceptionFactory,
        private ExceptionIdentifier $exceptionIdentifier,
    ) {
    }

    /**
     * @throws MachineActionFailedException
     */
    public function create(Machine $machine): RemoteMachineInterface
    {
        $exceptions = [];
        foreach ($this->providerMachineManagers as $machineManager) {
            try {
                return $machineManager->create(
                    $machine->getId(),
                    $this->machineNameFactory->create($machine->getId())
                );
            } catch (\Throwable $exception) {
                $exceptions[] = $this->exceptionFactory->create(
                    $machine->getId(),
                    MachineAction::CREATE,
                    $exception
                );
            }
        }

        throw new MachineActionFailedException($machine->getId(), MachineAction::CREATE, new Stack($exceptions));
    }

    /**
     * @throws ExceptionInterface
     * @throws UnsupportedProviderException
     * @throws ProviderMachineNotFoundException
     * @throws \Throwable
     */
    public function get(Machine $machine): RemoteMachineInterface
    {
        $machineProvider = $machine->getProvider();
        if (null === $machineProvider) {
            throw new UnsupportedProviderException($machineProvider);
        }

        $provider = $this->findProvider($machineProvider);
        if (null === $provider) {
            throw new UnsupportedProviderException($machineProvider);
        }

        $machineId = $machine->getId();

        try {
            $machine = $provider->get($machineId, $this->machineNameFactory->create($machineId));
            if ($machine instanceof RemoteMachineInterface) {
                return $machine;
            }
        } catch (\Throwable $exception) {
            if ($this->exceptionIdentifier->isMachineNotFoundException($exception)) {
                throw new ProviderMachineNotFoundException($machineId, $machineProvider->value);
            }

            throw $exception instanceof ExceptionInterface
                ? $exception
                : $this->exceptionFactory->create($machineId, MachineAction::GET, $exception);
        }

        throw new ProviderMachineNotFoundException($machineId, $machineProvider->value);
    }

    /**
     * @param non-empty-string $machineId
     *
     * @throws MachineActionFailedException
     */
    public function remove(string $machineId): void
    {
        $exceptions = [];
        foreach ($this->providerMachineManagers as $machineManager) {
            try {
                $machineManager->remove($machineId, $this->machineNameFactory->create($machineId));
            } catch (\Throwable $exception) {
                $newException = $this->exceptionFactory->create($machineId, MachineAction::DELETE, $exception);
                if (!$newException instanceof NotFoundRemoteMachineExceptionInterface) {
                    $exceptions[] = $newException;
                }
            }
        }

        if ([] !== $exceptions) {
            throw new MachineActionFailedException($machineId, MachineAction::DELETE, new Stack($exceptions));
        }
    }

    /**
     * @param non-empty-string $machineId
     *
     * @throws MachineActionFailedException
     */
    public function find(string $machineId): ?RemoteMachineInterface
    {
        $exceptions = [];
        foreach ($this->providerMachineManagers as $machineManager) {
            try {
                $remoteMachine = $machineManager->get($machineId, $this->machineNameFactory->create($machineId));

                if ($remoteMachine instanceof RemoteMachineInterface) {
                    return $remoteMachine;
                }
            } catch (\Throwable $exception) {
                $exceptions[] = $this->exceptionFactory->create($machineId, MachineAction::FIND, $exception);
            }
        }

        if ([] !== $exceptions) {
            throw new MachineActionFailedException($machineId, MachineAction::FIND, new Stack($exceptions));
        }

        return null;
    }

    private function findProvider(MachineProvider $machineProvider): ?ProviderMachineManagerInterface
    {
        foreach ($this->providerMachineManagers as $machineManager) {
            if ($machineManager->supports($machineProvider)) {
                return $machineManager;
            }
        }

        return null;
    }
}
