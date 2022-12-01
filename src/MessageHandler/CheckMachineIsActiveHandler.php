<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Message\CheckMachineIsActive;
use App\Repository\MachineRepository;
use App\Services\MachineRequestDispatcher;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class CheckMachineIsActiveHandler implements MessageHandlerInterface
{
    public function __construct(
        private MachineRequestDispatcher $machineRequestDispatcher,
        private readonly MachineRepository $machineRepository,
    ) {
    }

    public function __invoke(CheckMachineIsActive $message): void
    {
        $machine = $this->machineRepository->find($message->getMachineId());
        if (!$machine instanceof Machine) {
            return;
        }

        $state = $machine->getState();

        if (
            in_array($state, MachineState::END_STATES)
            || !in_array($state, MachineState::PRE_ACTIVE_STATES)
        ) {
            return;
        }

        $onSuccessRequests = $message->getOnSuccessCollection();
        $onSuccessRequests[] = $message;

        $this->machineRequestDispatcher->dispatchCollection($onSuccessRequests);
    }
}
