<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Machine;
use App\Model\RemoteMachineInterface;
use Symfony\Contracts\EventDispatcher\Event;

class MachineRetrievedEvent extends Event
{
    public function __construct(
        public readonly Machine $machine,
        public readonly RemoteMachineInterface $remoteMachine,
    ) {}
}
