<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Exception\RecoverableDeciderExceptionInterface;
use App\Exception\UnrecoverableExceptionInterface;
use App\Message\GetMachine;
use App\Services\Entity\Store\MachineProviderStore;
use App\Services\Entity\Store\MachineStore;
use App\Services\MachineManager;
use App\Services\MachineRequestDispatcher;
use App\Services\MachineUpdater;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class GetMachineHandler implements MessageHandlerInterface
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
    public function __invoke(GetMachine $message): void
    {
        $machine = $this->machineStore->find($message->getMachineId());
        if (!$machine instanceof Machine) {
            return;
        }

        $machineProvider = $this->machineProviderStore->find($message->getMachineId());
        if (!$machineProvider instanceof MachineProvider) {
            return;
        }

        try {
            $remoteMachine = $this->machineManager->get($machineProvider);
            $this->machineUpdater->updateFromRemoteMachine($machine, $remoteMachine);
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
