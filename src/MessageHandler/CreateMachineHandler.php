<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Event\MachineCreatedEvent;
use App\Exception\UnrecoverableExceptionInterface;
use App\Message\CreateMachine;
use App\Repository\MachineRepository;
use App\Services\MachineManager\MachineManager;
use App\Services\MachineMutator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class CreateMachineHandler
{
    public function __construct(
        private MachineManager $machineManager,
        private MachineRepository $machineRepository,
        private EventDispatcherInterface $eventDispatcher,
        private MachineMutator $machineMutator,
    ) {}

    /**
     * @throws \Throwable
     */
    public function __invoke(CreateMachine $message): void
    {
        $machine = $this->machineRepository->find($message->getMachineId());
        if (!$machine instanceof Machine) {
            return;
        }

        $this->machineMutator->setState($machine, MachineState::CREATE_REQUESTED);

        try {
            $remoteMachine = $this->machineManager->create($machine);

            $this->eventDispatcher->dispatch(new MachineCreatedEvent($machine, $remoteMachine));
        } catch (UnrecoverableExceptionInterface $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
