<?php

namespace App\EventListener\Messenger;

use App\Entity\Machine;
use App\Message\CreateMachine;
use App\Message\DeleteMachine;
use App\Message\FindMachine;
use App\Message\GetMachine;
use App\Message\MachineRequestInterface;
use App\Services\Entity\Factory\CreateFailureFactory;
use App\Services\Entity\Store\MachineStore;
use App\Services\MessageHandlerExceptionFinder;
use App\Services\MessageHandlerExceptionStackFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class MachineRequestFailureHandler implements EventSubscriberInterface
{
    public function __construct(
        private MachineStore $machineStore,
        private CreateFailureFactory $createFailureFactory,
        private MessageHandlerExceptionFinder $messageHandlerExceptionFinder,
        private MessageHandlerExceptionStackFactory $exceptionStackFactory,
        private LoggerInterface $messengerAuditLogger,
    ) {
    }

    /**
     * @return array<class-string, array<int, int|string>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => ['onMessageFailed', -100],
        ];
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if (true === $event->willRetry()) {
            return;
        }

        $envelope = $event->getEnvelope();
        $message = $envelope->getMessage();

        if (!($message instanceof MachineRequestInterface)) {
            return;
        }

        $machine = $this->machineStore->find($message->getMachineId());
        if (!$machine instanceof Machine) {
            return;
        }

        $messageHandlerException = $this->messageHandlerExceptionFinder->findInWorkerMessageFailedEvent($event);
        foreach ($this->exceptionStackFactory->create($messageHandlerException) as $loggableException) {
            $this->logException($message, $loggableException);
        }

        if ($message instanceof CreateMachine) {
            $machine->setState(Machine::STATE_CREATE_FAILED);
            $this->createFailureFactory->create($machine->getId(), $messageHandlerException);
        }

        if ($message instanceof DeleteMachine) {
            $machine->setState(Machine::STATE_DELETE_FAILED);
        }

        if ($message instanceof FindMachine) {
            $machine->setState(Machine::STATE_FIND_NOT_FINDABLE);
        }

        if ($message instanceof GetMachine) {
            $machine->setState(Machine::STATE_FIND_NOT_FOUND);
        }

        $this->machineStore->store($machine);
    }

    private function logException(MachineRequestInterface $message, \Throwable $throwable): void
    {
        $this->messengerAuditLogger->critical(
            $throwable->getMessage(),
            [
                'message_id' => $message->getUniqueId(),
                'machine_id' => $message->getMachineId(),
                'code' => $throwable->getCode(),
                'exception' => $throwable::class,
            ]
        );
    }
}
