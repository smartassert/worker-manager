<?php

namespace App\EventListener\Messenger;

use App\Entity\Machine;
use App\Exception\StackedExceptionInterface;
use App\Message\CreateMachine;
use App\Message\DeleteMachine;
use App\Message\FindMachine;
use App\Message\GetMachine;
use App\Message\MachineRequestInterface;
use App\Services\Entity\Factory\CreateFailureFactory;
use App\Services\Entity\Store\MachineStore;
use SmartAssert\InvokableLogger\ExceptionLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class MachineRequestFailureHandler implements EventSubscriberInterface
{
    public function __construct(
        private MachineStore $machineStore,
        private CreateFailureFactory $createFailureFactory,
        private ExceptionLogger $exceptionLogger,
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

        $throwable = $event->getThrowable();
        if ($throwable instanceof StackedExceptionInterface) {
            foreach ($throwable->getExceptionStack() as $exception) {
                $this->exceptionLogger->log($exception);
            }
        } else {
            $this->exceptionLogger->log($throwable);
        }

        if ($message instanceof CreateMachine) {
            $machine->setState(Machine::STATE_CREATE_FAILED);
            $this->createFailureFactory->create($machine->getId(), $throwable);
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
}
