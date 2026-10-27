<?php

declare(strict_types=1);

namespace App\EventDispatcher;

use App\Event\MachineStateChangedEvent;
use App\Event\MachineStateNotificationEvent;
use App\Model\Machine as MachineModel;
use App\Repository\ActionFailureRepository;
use App\Repository\MachineRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class MachineStateNotificationEventDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private MachineRepository $machineRepository,
        private ActionFailureRepository $actionFailureRepository,
    ) {}

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineStateChangedEvent::class => [
                ['dispatch', 500],
            ],
        ];
    }

    public function dispatch(MachineStateChangedEvent $event): void
    {
        $machine = $this->machineRepository->find($event->getMachineId());
        if (null === $machine) {
            return;
        }

        $notifyUrl = $machine->getNotifyUrl();
        if (null === $notifyUrl) {
            return;
        }

        $machineModel = new MachineModel(
            $machine,
            $this->actionFailureRepository->find($event->getMachineId())
        );

        $notificationEvent = new MachineStateNotificationEvent(
            $event->getMachine(),
            $machineModel,
            $notifyUrl,
        );

        $this->eventDispatcher->dispatch($notificationEvent);
    }
}
