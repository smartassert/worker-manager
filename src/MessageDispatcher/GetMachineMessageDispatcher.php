<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\MessageNotHandleableEvent;
use App\Message\GetMachine;
use App\ReadinessAssessor\GetMachineReadinessAssessor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class GetMachineMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private GetMachineReadinessAssessor $readinessAssessor,
        private MessageBusInterface $messageBus,
    ) {}

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MessageNotHandleableEvent::class => [
                ['redispatch', 100],
            ],
        ];
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

        $readiness = $this->readinessAssessor->isReady($message->getMachineId());
        if (MessageHandlingReadiness::NEVER === $readiness) {
            return;
        }

        $this->messageBus->dispatch($message);
    }
}
