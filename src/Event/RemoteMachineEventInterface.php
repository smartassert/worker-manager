<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Machine;
use App\Model\RemoteMachineInterface;

interface RemoteMachineEventInterface
{
    public function getMachine(): Machine;

    public function getRemoteMachine(): RemoteMachineInterface;
}
