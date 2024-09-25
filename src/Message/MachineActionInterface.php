<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\MachineAction;

interface MachineActionInterface
{
    public function getAction(): MachineAction;
}
