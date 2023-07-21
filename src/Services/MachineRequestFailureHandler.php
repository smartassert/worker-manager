<?php

namespace App\Services;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Message\CreateMachine;
use App\Message\DeleteMachine;
use App\Message\FindMachine;
use App\Message\GetMachine;
use App\Message\MachineRequestInterface;
use App\Repository\MachineRepository;
use App\Services\Entity\Factory\CreateFailureFactory;
use Psr\Log\LoggerInterface;
use SmartAssert\WorkerMessageFailedEventBundle\ExceptionHandlerInterface;
use Symfony\Component\Messenger\Envelope;

readonly class MachineRequestFailureHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private CreateFailureFactory $createFailureFactory,
        private MessageHandlerExceptionStackFactory $exceptionStackFactory,
        private LoggerInterface $messengerAuditLogger,
        private MachineRepository $machineRepository,
    ) {
    }

    public function handle(Envelope $envelope, \Throwable $throwable): void
    {
        $message = $envelope->getMessage();
        if (!$message instanceof MachineRequestInterface) {
            return;
        }

        $machine = $this->machineRepository->find($message->getMachineId());
        if (!$machine instanceof Machine) {
            return;
        }

        foreach ($this->exceptionStackFactory->create($throwable) as $loggableException) {
            $this->logException($message, $loggableException);
        }

        if ($message instanceof CreateMachine) {
            $machine->setState(MachineState::CREATE_FAILED);
            $this->createFailureFactory->create($machine->getId(), $throwable);
        }

        if ($message instanceof DeleteMachine) {
            $machine->setState(MachineState::DELETE_FAILED);
        }

        if ($message instanceof FindMachine) {
            $machine->setState(MachineState::FIND_NOT_FINDABLE);
        }

        if ($message instanceof GetMachine) {
            $machine->setState(MachineState::FIND_NOT_FOUND);
        }

        $this->machineRepository->add($machine);
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
