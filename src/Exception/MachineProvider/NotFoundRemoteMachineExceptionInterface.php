<?php

namespace App\Exception\MachineProvider;

use App\Enum\MachineProvider;

interface NotFoundRemoteMachineExceptionInterface extends ExceptionInterface
{
    public function getProvider(): MachineProvider;
}
