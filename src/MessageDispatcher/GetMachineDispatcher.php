<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\MachineCreatedEvent;
use App\Event\MessageNotHandleableEvent;
use App\Message\GetMachine;
use App\ReadinessAssessor\GetMachineReadinessAssessor;
use App\Services\MachineRequestFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class GetMachineDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private GetMachineReadinessAssessor $readinessAssessor,
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
            MessageNotHandleableEvent::class => [
                ['redispatch', 100],
            ],
        ];
    }

    /**
     * @throws ExceptionInterface
     */
    public function dispatch(MachineCreatedEvent $event): void
    {
        $this->doDispatch($event->machine->getId());
    }

    /**
     * @throws ExceptionInterface
     */
    public function redispatch(MessageNotHandleableEvent $event): void
    {
        $message = $event->message;
        if (!$message instanceof GetMachine) {
            return;
        }

        if (MessageHandlingReadiness::NEVER === $event->readiness) {
            return;
        }

        $this->doDispatch($message->getMachineId());
    }

    /**
     * @param non-empty-string $machineId
     *
     * @throws ExceptionInterface
     */
    private function doDispatch(string $machineId): void
    {
        $readiness = $this->readinessAssessor->isReady($machineId);
        if (MessageHandlingReadiness::NEVER === $readiness) {
            return;
        }

        $message = $this->machineRequestFactory->createGetMachine($machineId);

        $this->messageBus->dispatch($message);
    }
}
