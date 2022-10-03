<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Exception\RecoverableDeciderExceptionInterface;
use App\Exception\UnrecoverableExceptionInterface;
use App\Message\DeleteMachine;
use App\Repository\MachineRepository;
use App\Services\Entity\Store\MachineStore;
use App\Services\MachineRequestDispatcher;
use App\Services\RemoteMachineRemover;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class DeleteMachineHandler implements MessageHandlerInterface
{
    public function __construct(
        private MachineStore $machineStore,
        private RemoteMachineRemover $remoteMachineRemover,
        private MachineRequestDispatcher $machineRequestDispatcher,
        private readonly MachineRepository $machineRepository,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function __invoke(DeleteMachine $message): void
    {
        $machineId = $message->getMachineId();

        $machine = $this->machineRepository->find($machineId);
        if (!$machine instanceof Machine) {
            return;
        }

        $machine->setState(Machine::STATE_DELETE_REQUESTED);
        $this->machineStore->store($machine);

        try {
            $this->remoteMachineRemover->remove($machineId);
            $this->machineRequestDispatcher->dispatchCollection($message->getOnSuccessCollection());
        } catch (\Throwable $exception) {
            if (
                $exception instanceof UnrecoverableExceptionInterface
                || $exception instanceof RecoverableDeciderExceptionInterface && false === $exception->isRecoverable()
            ) {
                $code = $exception->getCode();
                $code = is_int($code) ? $code : 0;

                throw new UnrecoverableMessageHandlingException($exception->getMessage(), $code, $exception);
            }

            throw $exception;
        }
    }
}
