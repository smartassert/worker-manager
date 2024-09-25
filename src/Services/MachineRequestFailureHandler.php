<?php

namespace App\Services;

use App\Entity\Machine;
use App\Exception\MachineActionFailedException;
use App\Message\MachineActionInterface;
use App\Repository\MachineRepository;
use App\Services\Entity\Factory\ActionFailureFactory;
use Psr\Log\LoggerInterface;
use SmartAssert\WorkerMessageFailedEventBundle\ExceptionHandlerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

readonly class MachineRequestFailureHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private ActionFailureFactory $actionFailureFactory,
        private MessageHandlerExceptionStackFactory $exceptionStackFactory,
        private LoggerInterface $messengerAuditLogger,
        private MachineRepository $machineRepository,
    ) {
    }

    public function handle(Envelope $envelope, \Throwable $throwable): void
    {
        if ($throwable instanceof HandlerFailedException) {
            return;
        }

        $message = $envelope->getMessage();
        if (!$message instanceof MachineActionInterface) {
            return;
        }

        $machine = $this->machineRepository->find($message->getMachineId());
        if (!$machine instanceof Machine) {
            return;
        }

        if ($throwable instanceof UnrecoverableMessageHandlingException) {
            $throwable = $throwable->getPrevious() ?? $throwable;
        }

        foreach ($this->exceptionStackFactory->create($throwable) as $loggableException) {
            $this->messengerAuditLogger->critical(
                $loggableException->getMessage(),
                [
                    'message_id' => $message->getUniqueId(),
                    'machine_id' => $message->getMachineId(),
                    'code' => $loggableException->getCode(),
                    'exception' => $loggableException::class,
                ]
            );
        }

        $machine->setState($message->getFailureState());
        $this->machineRepository->add($machine);

        // @todo fix in #514
        if ($throwable instanceof MachineActionFailedException) {
            $throwable = $throwable->getExceptionStack()[0];
        }

        $this->actionFailureFactory->create($machine, $message->getAction(), $throwable);
    }
}
