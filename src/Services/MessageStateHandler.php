<?php

namespace App\Services;

use App\Entity\MessageState;
use App\Message\UniqueRequestInterface;
use App\Services\Entity\Store\MessageStateStore;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

class MessageStateHandler implements EventSubscriberInterface
{
    public function __construct(
        private MessageStateStore $messageStateStore,
    ) {
    }

    /**
     * @return array<class-string, array<array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SendMessageToTransportsEvent::class => [
                ['create', 100],
            ],
            WorkerMessageReceivedEvent::class => [
                ['setHandling', 100],
            ],
            WorkerMessageFailedEvent::class => [
                ['remove', 100],
            ],
            WorkerMessageHandledEvent::class => [
                ['remove', 100],
            ],
        ];
    }

    public function create(SendMessageToTransportsEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $message = $envelope->getMessage();

        if ($message instanceof UniqueRequestInterface) {
            $messageState = $this->messageStateStore->find($message->getUniqueId());

            if (null === $messageState) {
                $messageState = new MessageState($message->getUniqueId());
                $this->messageStateStore->store($messageState);
            }
        }
    }

    public function setHandling(WorkerMessageReceivedEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $message = $envelope->getMessage();

        if ($message instanceof UniqueRequestInterface) {
            $messageState = $this->messageStateStore->find($message->getUniqueId());

            if ($messageState instanceof MessageState) {
                $messageState->setState(MessageState::STATE_HANDLING);
                $this->messageStateStore->store($messageState);
            }
        }
    }

    public function remove(WorkerMessageFailedEvent | WorkerMessageHandledEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $message = $envelope->getMessage();

        if ($message instanceof UniqueRequestInterface) {
            $this->messageStateStore->remove($message->getUniqueId());
        }
    }
}
