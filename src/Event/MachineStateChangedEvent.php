<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\MachineState;

class MachineStateChangedEvent extends AbstractMachineEvent implements MachineEventInterface
{
    /**
     * @param non-empty-string $machineId
     */
    public function __construct(
        string $machineId,
        private readonly MachineState $newState,
    ) {
        parent::__construct($machineId);
    }

    public function getNewState(): MachineState
    {
        return $this->newState;
    }
}
