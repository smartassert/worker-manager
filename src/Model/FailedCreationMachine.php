<?php

namespace App\Model;

use App\Entity\CreateFailure;
use App\Entity\Machine;

class FailedCreationMachine extends Machine
{
    public function __construct(
        Machine $machine,
        private readonly CreateFailure $createFailure,
    ) {
        parent::__construct($machine->getId(), $machine->getState(), $machine->getIpAddresses());
    }

    public function jsonSerialize(): array
    {
        return array_merge(
            parent::jsonSerialize(),
            ['create_failure' => $this->createFailure],
        );
    }
}
