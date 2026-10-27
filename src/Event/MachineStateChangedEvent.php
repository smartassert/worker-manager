<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Machine;
use App\Enum\MachineState;

class MachineStateChangedEvent extends AbstractMachineEvent implements MachineEventInterface
{
    public function __construct(
        Machine $machine,
        private readonly MachineState $newState,
    ) {
        parent::__construct($machine);
    }

    public function getNewState(): MachineState
    {
        return $this->newState;
    }
}
