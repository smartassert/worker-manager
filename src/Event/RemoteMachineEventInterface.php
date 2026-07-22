<?php

declare(strict_types=1);

namespace App\Event;

use App\Model\RemoteMachineInterface;

interface RemoteMachineEventInterface extends MachineEventInterface
{
    public function getRemoteMachine(): RemoteMachineInterface;
}
