<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Enum\MessageHandlingReadiness;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineStateChangedEvent;
use App\Exception\UnrecoverableExceptionInterface;
use App\Message\FindMachineForRetrieval;
use App\Model\DigitalOcean\RemoteMachine;
use App\ReadinessAssessor\FindMachineReadinessAssessor;
use App\Repository\MachineRepository;
use App\Services\MachineManager\MachineManager;
use App\Services\UnhandleableMessageHandler;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class FindMachineForRetrievalHandler
{
    public function __construct(
        private FindMachineReadinessAssessor $readinessAssessor,
        private UnhandleableMessageHandler $unhandleableMessageHandler,
        private MachineManager $machineManager,
        private MachineRepository $machineRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @throws \Throwable
     */
    public function __invoke(FindMachineForRetrieval $message): void
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

        $this->eventDispatcher->dispatch(new MachineStateChangedEvent($machine, MachineState::FIND_FINDING));

        try {
            $remoteMachine = $this->machineManager->find($message->getMachineId());
        } catch (UnrecoverableExceptionInterface $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), $e->getCode(), $e);
        }

        if (!$remoteMachine instanceof RemoteMachine) {
            return;
        }

        $remoteMachineState = $remoteMachine->getState();

        if (null !== $remoteMachineState && $machine->getState() !== $remoteMachineState) {
            $this->eventDispatcher->dispatch(new MachineStateChangedEvent($machine, $remoteMachineState));
        }

        $this->eventDispatcher->dispatch(new MachineRetrievedEvent($machine, $remoteMachine));
    }
}
