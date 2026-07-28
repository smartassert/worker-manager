<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Machine;
use App\Enum\MachineState;
use Symfony\Contracts\EventDispatcher\Event;

class MachineStateChangedEvent extends Event implements MachineEventInterface
{
    public function __construct(
        private readonly Machine $machine,
        private readonly MachineState $newState,
    ) {}

    public function getMachine(): Machine
    {
        return $this->machine;
    }

    public function getNewState(): MachineState
    {
        return $this->newState;
    }
}
