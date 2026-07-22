<?php

namespace App\Services;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Event\MachineCreatedEvent;
use App\Model\RemoteMachineInterface;
use App\Repository\MachineRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class MachineUpdater implements EventSubscriberInterface
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
                ['updateFromMachineCreatedEvent', 1000],
            ],
        ];
    }

    public function updateFromRemoteMachine(Machine $machine, RemoteMachineInterface $remoteMachine): Machine
    {
        $machine->setState($remoteMachine->getState() ?? MachineState::CREATE_REQUESTED);
        $machine->setIpAddresses($remoteMachine->getIpAddresses());
        $machine->setProvider($remoteMachine->getProvider());
        $this->machineRepository->add($machine);

        return $machine;
    }

    public function updateFromMachineCreatedEvent(MachineCreatedEvent $event): void
    {
        $this->updateFromRemoteMachine($event->machine, $event->remoteMachine);
    }
}
