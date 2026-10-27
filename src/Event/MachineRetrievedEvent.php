<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Machine;
use App\Model\RemoteMachineInterface;

class MachineRetrievedEvent extends AbstractMachineEvent implements RemoteMachineEventInterface
{
    public function __construct(
        Machine $machine,
        private readonly RemoteMachineInterface $remoteMachine,
    ) {
        parent::__construct($machine);
    }

    public function getRemoteMachine(): RemoteMachineInterface
    {
        return $this->remoteMachine;
    }
}
