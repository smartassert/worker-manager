<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\MachineCreatedEvent;
use App\Services\MachineRequestFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class GetMachineDispatcher implements EventSubscriberInterface
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
            MachineCreatedEvent::class => [
                ['dispatch', 100],
            ],
        ];
    }

    /**
     * @throws ExceptionInterface
     */
    public function dispatch(MachineCreatedEvent $event): void
    {
        $message = $this->machineRequestFactory->createGetMachine(
            $event->machine->getId(),
        );

        $this->messageBus->dispatch($message);
    }
}
