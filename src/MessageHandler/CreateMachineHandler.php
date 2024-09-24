<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Exception\UnrecoverableExceptionInterface;
use App\Message\CreateMachine;
use App\Repository\MachineRepository;
use App\Services\MachineManager\MachineManager;
use App\Services\MachineRequestDispatcher;
use App\Services\MachineUpdater;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class CreateMachineHandler
{
    public function __construct(
        private MachineManager $machineManager,
        private MachineRequestDispatcher $machineRequestDispatcher,
        private MachineUpdater $machineUpdater,
        private readonly MachineRepository $machineRepository,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function __invoke(CreateMachine $message): void
    {
        $machine = $this->machineRepository->find($message->getMachineId());
        if (!$machine instanceof Machine) {
            return;
        }

        $machine->setState(MachineState::CREATE_REQUESTED);
        $this->machineRepository->add($machine);

        try {
            $remoteMachine = $this->machineManager->create($machine);
            $this->machineUpdater->updateFromRemoteMachine($machine, $remoteMachine);
            $this->machineRequestDispatcher->dispatchCollection($message->getOnSuccessCollection());
        } catch (UnrecoverableExceptionInterface $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
