<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Message\CheckMachineIsActive;
use App\Repository\MachineRepository;
use App\Services\MachineRequestDispatcher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler]
class CheckMachineIsActiveHandler
{
    public function __construct(
        private MachineRequestDispatcher $machineRequestDispatcher,
        private readonly MachineRepository $machineRepository,
    ) {}

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(CheckMachineIsActive $message): void
    {
        $machine = $this->machineRepository->find($message->getMachineId());
        if (!$machine instanceof Machine) {
            return;
        }

        $state = $machine->getState();

        if (MachineState::isEnd($state) || !MachineState::isPreActive($state)) {
            return;
        }

        $onSuccessRequests = $message->getOnSuccessCollection();
        $onSuccessRequests[] = $message;

        $this->machineRequestDispatcher->dispatchCollection($onSuccessRequests);
    }
}
