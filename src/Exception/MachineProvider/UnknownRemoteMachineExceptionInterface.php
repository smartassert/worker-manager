<?php

namespace App\Exception\MachineProvider;

use App\Enum\MachineProvider;
use App\Exception\RecoverableDeciderExceptionInterface;

interface UnknownRemoteMachineExceptionInterface extends ExceptionInterface, RecoverableDeciderExceptionInterface
{
    public function getProvider(): MachineProvider;
}
