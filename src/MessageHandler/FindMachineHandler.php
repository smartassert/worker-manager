<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Exception\MachineNotFindableException;
use App\Message\FindMachine;
use App\Model\RemoteMachineInterface;
use App\Services\Entity\Store\MachineProviderStore;
use App\Services\Entity\Store\MachineStore;
use App\Services\MachineRequestDispatcher;
use App\Services\MachineUpdater;
use App\Services\RemoteMachineFinder;
use SmartAssert\InvokableLogger\ExceptionLogger;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use webignition\SymfonyMessengerMessageDispatcher\MessageDispatcher;

class FindMachineHandler implements MessageHandlerInterface
{
    public function __construct(
        private MachineStore $machineStore,
        private MachineProviderStore $machineProviderStore,
        private RemoteMachineFinder $remoteMachineFinder,
        private MachineUpdater $machineUpdater,
        private MachineRequestDispatcher $machineRequestDispatcher,
        private ExceptionLogger $exceptionLogger,
    ) {
    }

    public function __invoke(FindMachine $message): void
    {
        $machineId = $message->getMachineId();

        $machine = $this->machineStore->find($machineId);
        if (!$machine instanceof Machine) {
            return;
        }

        $machine->setState(Machine::STATE_FIND_FINDING);
        $this->machineStore->store($machine);

        try {
            $remoteMachine = $this->remoteMachineFinder->find($machineId);

            if ($remoteMachine instanceof RemoteMachineInterface) {
                $this->machineUpdater->updateFromRemoteMachine($machine, $remoteMachine);

                $machineProvider = new MachineProvider($machineId, $remoteMachine->getProvider());
                $this->machineProviderStore->store($machineProvider);

                $onSuccessCollection = $message->getOnSuccessCollection();

                if ($message->getReDispatchOnSuccess()) {
                    $message = $message->incrementRetryCount();
                    $onSuccessCollection[] = $message;
                }

                $this->machineRequestDispatcher->dispatchCollection($onSuccessCollection);
            } else {
                $machine->setState($message->getOnNotFoundState());
                $this->machineStore->store($machine);

                $this->machineRequestDispatcher->dispatchCollection($message->getOnFailureCollection());
            }
        } catch (MachineNotFindableException $machineNotFoundException) {
            $envelope = $this->machineRequestDispatcher->reDispatch($message);

            if (false === MessageDispatcher::isDispatchable($envelope)) {
                foreach ($machineNotFoundException->getExceptionStack() as $exception) {
                    $this->exceptionLogger->log($exception);
                }

                $machine->setState(Machine::STATE_FIND_NOT_FINDABLE);
                $this->machineStore->store($machine);
            }
        }
    }
}
