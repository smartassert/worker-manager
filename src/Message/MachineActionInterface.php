<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\MachineAction;
use App\Enum\MachineState;

interface MachineActionInterface extends MachineRequestInterface
{
    public function getAction(): MachineAction;

    public function getFailureState(): MachineState;
}
