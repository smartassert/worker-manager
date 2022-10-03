<?php

namespace App\Services;

use App\Entity\Machine;
use App\Model\RemoteMachineInterface;
use App\Repository\MachineRepository;

class MachineUpdater
{
    public function __construct(
        private readonly MachineRepository $machineRepository,
    ) {
    }

    public function updateFromRemoteMachine(Machine $machine, RemoteMachineInterface $remoteMachine): Machine
    {
        $machine->setState($remoteMachine->getState() ?? Machine::STATE_CREATE_REQUESTED);
        $machine->setIpAddresses($remoteMachine->getIpAddresses());
        $this->machineRepository->add($machine);

        return $machine;
    }
}
