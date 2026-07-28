<?php

namespace App\Services;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Enum\MachineStateCategory;
use App\Event\CreateMachineEvent;
use App\Event\MachineCreatedEvent;
use App\Event\MachineDeletedEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineStateChangedEvent;
use App\Event\MachineTerminatedEvent;
use App\Event\RemoteMachineEventInterface;
use App\Model\RemoteMachineInterface;
use App\Repository\MachineRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class MachineMutator implements EventSubscriberInterface
{
    public function __construct(
        private MachineRepository $machineRepository,
    ) {}

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineCreatedEvent::class => [
                ['updateFromRemoteMachineEvent', 1000],
            ],
            MachineRetrievedEvent::class => [
                ['updateFromRemoteMachineEvent', 1000],
            ],
            CreateMachineEvent::class => [
                ['setStateForCreateMachineEvent', 1000],
            ],
            MachineDeletedEvent::class => [
                ['setStateForMachineDeletedEvent', 1000],
            ],
            MachineTerminatedEvent::class => [
                ['setStateForMachineTerminatedEvent', 1000],
            ],
            MachineStateChangedEvent::class => [
                ['setStateForMachineStateChangedEvent', 2000],
            ],
        ];
    }

    public function updateFromRemoteMachine(Machine $machine, RemoteMachineInterface $remoteMachine): Machine
    {
        $machine->setIpAddresses($remoteMachine->getIpAddresses());
        $machine->setProvider($remoteMachine->getProvider());
        $this->machineRepository->add($machine);

        return $machine;
    }

    public function updateFromRemoteMachineEvent(RemoteMachineEventInterface $event): void
    {
        $this->updateFromRemoteMachine($event->getMachine(), $event->getRemoteMachine());
    }

    public function setStateForCreateMachineEvent(CreateMachineEvent $event): void
    {
        $this->setState($event->getMachine(), MachineState::CREATE_RECEIVED);
    }

    public function setStateForMachineDeletedEvent(MachineDeletedEvent $event): void
    {
        $this->setState($event->getMachine(), MachineState::DELETE_REQUESTED);
    }

    public function setStateForMachineTerminatedEvent(MachineTerminatedEvent $event): void
    {
        $this->setState($event->getMachine(), MachineState::DELETE_DELETED);
    }

    public function setStateForMachineStateChangedEvent(MachineStateChangedEvent $event): void
    {
        $this->setState($event->getMachine(), $event->getNewState());
    }

    private function setState(Machine $machine, MachineState $newState): void
    {
        $currentStateCategory = MachineStateCategory::fromState($machine->getState());
        $nextStateCategory = MachineStateCategory::fromState($newState);

        if (
            !$machine->getState()->isResettable()
            && !$currentStateCategory->hasNext($nextStateCategory)
        ) {
            return;
        }

        $machine->setState($newState);
        $this->machineRepository->add($machine);
    }
}
