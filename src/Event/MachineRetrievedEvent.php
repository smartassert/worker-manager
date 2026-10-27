<?php

declare(strict_types=1);

namespace App\Event;

use App\Model\RemoteMachineInterface;

class MachineRetrievedEvent extends AbstractMachineEvent implements RemoteMachineEventInterface
{
    /**
     * @param non-empty-string $machineId
     */
    public function __construct(
        string $machineId,
        private readonly RemoteMachineInterface $remoteMachine,
    ) {
        parent::__construct($machineId);
    }

    public function getRemoteMachine(): RemoteMachineInterface
    {
        return $this->remoteMachine;
    }
}
