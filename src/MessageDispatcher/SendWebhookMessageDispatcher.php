<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\MachineStateChangedEvent;
use App\Event\NotifiableEventInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface as MessengerExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Webhook\Messenger\SendWebhookMessage;
use Symfony\Component\Webhook\Subscriber;

readonly class SendWebhookMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private string $secret,
    ) {}

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineStateChangedEvent::class => [
                ['dispatch', 0],
            ],
        ];
    }

    /**
     * @throws MessengerExceptionInterface
     */
    public function dispatch(NotifiableEventInterface $event): void
    {
        $notifyUrl = $event->getNotifyUrl();
        if (null === $notifyUrl) {
            return;
        }

        $subscriber = new Subscriber($notifyUrl, $this->secret);

        $remoteEvent = new RemoteEvent(
            name: $event->getRemoteEventName(),
            id: (string) new Ulid(),
            payload: $event->getPayload(),
        );

        $this->messageBus->dispatch(
            new SendWebhookMessage($subscriber, $remoteEvent),
        );
    }
}
