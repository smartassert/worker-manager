<?php

namespace App\Exception\MachineProvider;

use App\Enum\MachineProvider;
use App\Exception\UnrecoverableExceptionInterface;

interface AuthenticationExceptionInterface extends ExceptionInterface, UnrecoverableExceptionInterface
{
    public function getMachineProvider(): MachineProvider;
}
