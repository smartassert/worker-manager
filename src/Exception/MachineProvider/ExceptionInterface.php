<?php

namespace App\Exception\MachineProvider;

use App\Enum\MachineAction;

interface ExceptionInterface extends \Throwable
{
    public function getRemoteException(): \Throwable;

    public function getAction(): MachineAction;
}
