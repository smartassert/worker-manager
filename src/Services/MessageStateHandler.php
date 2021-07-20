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

    public static function getSubscribedEvents()
    {
        return [
            SendMessageToTransportsEvent::class => [
                ['createMessageState', 100],
            ],
            WorkerMessageReceivedEvent::class => [
                ['setMessageStateHandling', 100],
            ],
            WorkerMessageFailedEvent::class => [
                ['setMessageStateHandled', 100],
            ],
            WorkerMessageHandledEvent::class => [
                ['setMessageStateHandled', 100],
            ],
        ];
    }

    public function createMessageState(SendMessageToTransportsEvent $event): void
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

    public function setMessageStateHandling(WorkerMessageReceivedEvent $event): void
    {
        $this->setMessageState($event, MessageState::STATE_HANDLING);
    }

    public function setMessageStateHandled(WorkerMessageFailedEvent | WorkerMessageHandledEvent $event): void
    {
        $this->setMessageState($event, MessageState::STATE_HANDLED);
    }

    /**
     * @param MessageState::STATE_* $state
     */
    private function setMessageState(
        WorkerMessageReceivedEvent | WorkerMessageFailedEvent | WorkerMessageHandledEvent $event,
        string $state
    ): void {
        $envelope = $event->getEnvelope();
        $message = $envelope->getMessage();

        if ($message instanceof UniqueRequestInterface) {
            $messageState = $this->messageStateStore->find($message->getUniqueId());

            if ($messageState instanceof MessageState) {
                $messageState->setState($state);
                $this->messageStateStore->store($messageState);
            }
        }
    }
}
