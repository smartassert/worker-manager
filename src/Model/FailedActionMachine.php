<?php

namespace App\Model;

use App\Entity\ActionFailure;
use App\Entity\Machine;

class FailedActionMachine extends Machine
{
    public function __construct(
        Machine $machine,
        private readonly ActionFailure $actionFailure,
    ) {
        parent::__construct($machine->getId(), $machine->getState(), $machine->getIpAddresses());
    }

    public function jsonSerialize(): array
    {
        return array_merge(
            parent::jsonSerialize(),
            ['action_failure' => $this->actionFailure],
        );
    }
}
