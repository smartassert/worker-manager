<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\UnsupportedProviderException;
use App\Message\CreateMachine;
use App\Services\Entity\Factory\CreateFailureFactory;
use App\Services\Entity\Store\MachineProviderStore;
use App\Services\Entity\Store\MachineStore;
use App\Services\MachineManager;
use App\Services\MachineRequestDispatcher;
use App\Services\MachineUpdater;
use App\Services\RemoteRequestRetryDecider;
use SmartAssert\InvokableLogger\ExceptionLogger;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use webignition\SymfonyMessengerMessageDispatcher\MessageDispatcher;

class CreateMachineHandler implements MessageHandlerInterface
{
    public function __construct(
        private MachineManager $machineManager,
        private RemoteRequestRetryDecider $retryDecider,
        private ExceptionLogger $exceptionLogger,
        private MachineStore $machineStore,
        private MachineProviderStore $machineProviderStore,
        private MachineRequestDispatcher $machineRequestDispatcher,
        private CreateFailureFactory $createFailureFactory,
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

        $lastException = null;

        try {
            $remoteMachine = $this->machineManager->create($machineProvider);
            $this->machineUpdater->updateFromRemoteMachine($machine, $remoteMachine);
            $this->machineRequestDispatcher->dispatchCollection($message->getOnSuccessCollection());
        } catch (ExceptionInterface $exception) {
            $shouldRetry = $this->retryDecider->decide(
                $machineProvider->getName(),
                $message,
                $exception->getRemoteException()
            );

            $lastException = $exception;

            if ($shouldRetry) {
                $envelope = $this->machineRequestDispatcher->reDispatch($message);

                if (MessageDispatcher::isDispatchable($envelope)) {
                    $lastException = null;
                }
            }
        } catch (UnsupportedProviderException $unsupportedProviderException) {
            $lastException = $unsupportedProviderException;
        }

        if ($lastException instanceof \Throwable) {
            $machine->setState(Machine::STATE_CREATE_FAILED);
            $this->machineStore->store($machine);

            $this->createFailureFactory->create($machine->getId(), $lastException);

            $this->exceptionLogger->log($lastException);
        }
    }
}
