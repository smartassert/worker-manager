<?php

namespace App\Exception\MachineProvider;

use App\Enum\MachineProvider;
use App\Exception\StackedExceptionInterface;
use App\Exception\UnrecoverableExceptionInterface;

interface AuthenticationExceptionInterface extends
    ExceptionInterface,
    UnrecoverableExceptionInterface,
    StackedExceptionInterface
{
    public function getMachineProvider(): MachineProvider;
}
