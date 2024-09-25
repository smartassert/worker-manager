<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\MachineAction;

interface MachineActionInterface extends MachineRequestInterface
{
    public function getAction(): MachineAction;
}
