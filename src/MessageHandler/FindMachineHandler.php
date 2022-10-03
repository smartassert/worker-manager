<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Exception\RecoverableDeciderExceptionInterface;
use App\Exception\UnrecoverableExceptionInterface;
use App\Message\FindMachine;
use App\Model\RemoteMachineInterface;
use App\Repository\MachineRepository;
use App\Services\Entity\Store\MachineProviderStore;
use App\Services\MachineRequestDispatcher;
use App\Services\MachineUpdater;
use App\Services\RemoteMachineFinder;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class FindMachineHandler implements MessageHandlerInterface
{
    public function __construct(
        private MachineProviderStore $machineProviderStore,
        private RemoteMachineFinder $remoteMachineFinder,
        private MachineUpdater $machineUpdater,
        private MachineRequestDispatcher $machineRequestDispatcher,
        private readonly MachineRepository $machineRepository,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function __invoke(FindMachine $message): void
    {
        $machineId = $message->getMachineId();

        $machine = $this->machineRepository->find($machineId);
        if (!$machine instanceof Machine) {
            return;
        }

        $machine->setState(Machine::STATE_FIND_FINDING);
        $this->machineRepository->add($machine);

        try {
            $remoteMachine = $this->remoteMachineFinder->find($machineId);

            if ($remoteMachine instanceof RemoteMachineInterface) {
                $this->machineUpdater->updateFromRemoteMachine($machine, $remoteMachine);

                $machineProvider = new MachineProvider($machineId, $remoteMachine->getProvider());
                $this->machineProviderStore->store($machineProvider);

                $onSuccessCollection = $message->getOnSuccessCollection();

                if ($message->getReDispatchOnSuccess()) {
                    $onSuccessCollection[] = $message;
                }

                $this->machineRequestDispatcher->dispatchCollection($onSuccessCollection);
            } else {
                $machine->setState($message->getOnNotFoundState());
                $this->machineRepository->add($machine);

                $this->machineRequestDispatcher->dispatchCollection($message->getOnFailureCollection());
            }
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
