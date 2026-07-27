<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\MachineDeletedEvent;
use App\Services\MachineRequestFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class FindMachineAfterDeletionDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private MachineRequestFactory $machineRequestFactory,
        private MessageBusInterface $messageBus,
    ) {}

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineDeletedEvent::class => [
                ['dispatchForMachineDeletedEvent', 100],
            ],
        ];
    }

    /**
     * @throws ExceptionInterface
     */
    public function dispatchForMachineDeletedEvent(MachineDeletedEvent $event): void
    {
        $message = $this
            ->machineRequestFactory
            ->createFindMachineAfterDeletion($event->getMachine()->getId())
        ;

        $this->messageBus->dispatch($message);
    }
}
