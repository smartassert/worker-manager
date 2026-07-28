<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Enum\MessageHandlingReadiness;
use App\Event\MachineDeletedEvent;
use App\Event\MachineTerminatedEvent;
use App\Exception\UnrecoverableExceptionInterface;
use App\Message\FindMachineAfterDeletion;
use App\Model\DigitalOcean\RemoteMachine;
use App\ReadinessAssessor\FindMachineAfterDeletionReadinessAssessor;
use App\Repository\MachineRepository;
use App\Services\MachineManager\MachineManager;
use App\Services\MachineMutator;
use App\Services\UnhandleableMessageHandler;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class FindMachineAfterDeletionHandler
{
    public function __construct(
        private FindMachineAfterDeletionReadinessAssessor $readinessAssessor,
        private UnhandleableMessageHandler $unhandleableMessageHandler,
        private MachineManager $machineManager,
        private MachineRepository $machineRepository,
        private EventDispatcherInterface $eventDispatcher,
        private MachineMutator $machineMutator,
    ) {}

    /**
     * @throws \Throwable
     */
    public function __invoke(FindMachineAfterDeletion $message): void
    {
        $readiness = $this->readinessAssessor->isReady($message->getMachineId());
        if (MessageHandlingReadiness::NOW !== $readiness) {
            $this->unhandleableMessageHandler->handle($message, $readiness);

            return;
        }

        $machine = $this->machineRepository->find($message->getMachineId());
        if (!$machine instanceof Machine) {
            return;
        }

        $this->machineMutator->setState($machine, MachineState::FIND_FINDING);

        try {
            $remoteMachine = $this->machineManager->find($message->getMachineId());
        } catch (UnrecoverableExceptionInterface $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), $e->getCode(), $e);
        }

        if (!$remoteMachine instanceof RemoteMachine) {
            $this->eventDispatcher->dispatch(new MachineTerminatedEvent($machine));

            return;
        }

        $this->eventDispatcher->dispatch(new MachineDeletedEvent($machine));
    }
}
