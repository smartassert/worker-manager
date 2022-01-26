<?php

namespace App\Services;

use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class MessageHandlerExceptionFinder
{
    public function findInWorkerMessageFailedEvent(WorkerMessageFailedEvent $event): \Throwable
    {
        $eventThrowable = $event->getThrowable();
        if (!$eventThrowable instanceof HandlerFailedException) {
            return $eventThrowable;
        }

        $eventEncapsulatedThrowable = $eventThrowable->getNestedExceptions()[0];
        if (!$eventEncapsulatedThrowable instanceof UnrecoverableMessageHandlingException) {
            return $eventEncapsulatedThrowable;
        }

        $messageHandlerException = $eventEncapsulatedThrowable->getPrevious();

        return $messageHandlerException instanceof \Throwable ? $messageHandlerException : $eventEncapsulatedThrowable;
    }
}
