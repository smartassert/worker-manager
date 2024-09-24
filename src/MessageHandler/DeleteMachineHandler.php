<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Exception\UnrecoverableExceptionInterface;
use App\Message\DeleteMachine;
use App\Repository\MachineRepository;
use App\Services\MachineManager\MachineManager;
use App\Services\MachineRequestDispatcher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class DeleteMachineHandler
{
    public function __construct(
        private MachineManager $machineManager,
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

        $machine->setState(MachineState::DELETE_REQUESTED);
        $this->machineRepository->add($machine);

        try {
            $this->machineManager->remove($machineId);
            $this->machineRequestDispatcher->dispatchCollection($message->getOnSuccessCollection());
        } catch (UnrecoverableExceptionInterface $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
