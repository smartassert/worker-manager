<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\MachineProvider\ProviderMachineNotFoundException;
use App\Exception\UnsupportedProviderException;
use App\Message\GetMachine;
use App\Services\Entity\Store\MachineProviderStore;
use App\Services\Entity\Store\MachineStore;
use App\Services\MachineManager;
use App\Services\MachineRequestDispatcher;
use App\Services\MachineUpdater;
use App\Services\RemoteRequestRetryDecider;
use SmartAssert\InvokableLogger\ExceptionLogger;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use webignition\SymfonyMessengerMessageDispatcher\MessageDispatcher;

class GetMachineHandler implements MessageHandlerInterface
{
    public function __construct(
        private MachineManager $machineManager,
        private RemoteRequestRetryDecider $retryDecider,
        private ExceptionLogger $exceptionLogger,
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

        $lastException = null;

        try {
            $remoteMachine = $this->machineManager->get($machineProvider);
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
        } catch (ProviderMachineNotFoundException $machineNotFoundException) {
            $machine->setState(Machine::STATE_FIND_NOT_FOUND);
            $this->machineStore->store($machine);

            $lastException = $machineNotFoundException;
        }

        if ($lastException instanceof \Throwable) {
            $this->exceptionLogger->log($lastException);
        }
    }
}
