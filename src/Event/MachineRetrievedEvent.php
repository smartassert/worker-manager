<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Machine;
use App\Model\RemoteMachineInterface;
use Symfony\Contracts\EventDispatcher\Event;

class MachineRetrievedEvent extends Event implements RemoteMachineEventInterface
{
    public function __construct(
        private readonly Machine $machine,
        private readonly RemoteMachineInterface $remoteMachine,
    ) {}

    public function getMachine(): Machine
    {
        return $this->machine;
    }

    public function getRemoteMachine(): RemoteMachineInterface
    {
        return $this->remoteMachine;
    }
}
