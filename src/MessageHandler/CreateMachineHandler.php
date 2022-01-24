<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Exception\RecoverableDeciderExceptionInterface;
use App\Exception\UnrecoverableExceptionInterface;
use App\Message\CreateMachine;
use App\Services\Entity\Store\MachineProviderStore;
use App\Services\Entity\Store\MachineStore;
use App\Services\MachineManager;
use App\Services\MachineRequestDispatcher;
use App\Services\MachineUpdater;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class CreateMachineHandler implements MessageHandlerInterface
{
    public function __construct(
        private MachineManager $machineManager,
        private MachineStore $machineStore,
        private MachineProviderStore $machineProviderStore,
        private MachineRequestDispatcher $machineRequestDispatcher,
        private MachineUpdater $machineUpdater,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function __invoke(CreateMachine $message): void
    {
        $machine = $this->machineStore->find($message->getMachineId());
        if (!$machine instanceof Machine) {
            return;
        }

        $machineProvider = $this->machineProviderStore->find($message->getMachineId());
        if (!$machineProvider instanceof MachineProvider) {
            return;
        }

        $machine->setState(Machine::STATE_CREATE_REQUESTED);
        $this->machineStore->store($machine);

        try {
            $remoteMachine = $this->machineManager->create($machineProvider);
            $this->machineUpdater->updateFromRemoteMachine($machine, $remoteMachine);
            $this->machineRequestDispatcher->dispatchCollection($message->getOnSuccessCollection());
        } catch (\Throwable $exception) {
            if (
                $exception instanceof UnrecoverableExceptionInterface
                || $exception instanceof RecoverableDeciderExceptionInterface && false === $exception->isRecoverable()
            ) {
                throw new UnrecoverableMessageHandlingException(
                    'message',
                    0,
                    $exception
                );
            }

            throw $exception;
        }
    }
}
