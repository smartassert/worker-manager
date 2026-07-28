<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Enum\MessageHandlingReadiness;
use App\Event\CreateMachineEvent;
use App\Event\MachineStateChangedEvent;
use App\Exception\UnrecoverableExceptionInterface;
use App\Message\FindMachineForCreation;
use App\Model\DigitalOcean\RemoteMachine;
use App\ReadinessAssessor\CreateMachineReadinessAssessor;
use App\Repository\MachineRepository;
use App\Services\MachineManager\MachineManager;
use App\Services\UnhandleableMessageHandler;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class FindMachineForCreationHandler
{
    public function __construct(
        private CreateMachineReadinessAssessor $readinessAssessor,
        private UnhandleableMessageHandler $unhandleableMessageHandler,
        private MachineManager $machineManager,
        private MachineRepository $machineRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @throws \Throwable
     */
    public function __invoke(FindMachineForCreation $message): void
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

        if ($remoteMachine instanceof RemoteMachine) {
            return;
        }

        $this->eventDispatcher->dispatch(new CreateMachineEvent($machine));
    }
}
