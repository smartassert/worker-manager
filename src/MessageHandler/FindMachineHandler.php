<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Exception\UnrecoverableExceptionInterface;
use App\Message\FindMachine;
use App\Model\RemoteMachineInterface;
use App\Repository\MachineRepository;
use App\Services\MachineManager\MachineManager;
use App\Services\MachineRequestDispatcher;
use App\Services\MachineUpdater;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class FindMachineHandler
{
    public function __construct(
        private MachineManager $machineManager,
        private MachineUpdater $machineUpdater,
        private MachineRequestDispatcher $machineRequestDispatcher,
        private readonly MachineRepository $machineRepository,
    ) {}

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

        $machine->setState(MachineState::FIND_FINDING);
        $this->machineRepository->add($machine);

        try {
            $remoteMachine = $this->machineManager->find($machineId);

            if ($remoteMachine instanceof RemoteMachineInterface) {
                $this->machineUpdater->updateFromRemoteMachine($machine, $remoteMachine);

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
        } catch (UnrecoverableExceptionInterface $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
