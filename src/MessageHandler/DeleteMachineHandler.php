<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Event\MachineDeletedEvent;
use App\Exception\UnrecoverableExceptionInterface;
use App\Message\DeleteMachine;
use App\Repository\MachineRepository;
use App\Services\MachineManager\MachineManager;
use App\Services\MachineMutator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class DeleteMachineHandler
{
    public function __construct(
        private MachineManager $machineManager,
        private MessageBusInterface $messageBus,
        private readonly MachineRepository $machineRepository,
        private EventDispatcherInterface $eventDispatcher,
        private MachineMutator $machineMutator,
    ) {}

    /**
     * @throws \Throwable
     */
    public function __invoke(DeleteMachine $message): void
    {
        $machineId = $message->getMachineId();

        $machine = $this->machineRepository->find($machineId);
        if (!$machine instanceof Machine) {
            return;
        }

        $this->machineMutator->setState($machine, MachineState::DELETE_REQUESTED);

        try {
            $this->machineManager->remove($machineId);
            $this->eventDispatcher->dispatch(new MachineDeletedEvent($machine));

            foreach ($message->getOnSuccessCollection() as $onSuccessRequest) {
                $this->messageBus->dispatch($onSuccessRequest);
            }
        } catch (UnrecoverableExceptionInterface $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
