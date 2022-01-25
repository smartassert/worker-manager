<?php

declare(strict_types=1);

namespace App\Message;

use App\Model\MachineActionInterface;

interface RemoteMachineMessageInterface extends MachineRequestInterface
{
    /**
     * @return MachineActionInterface::ACTION_*
     */
    public function getAction(): string;
}
