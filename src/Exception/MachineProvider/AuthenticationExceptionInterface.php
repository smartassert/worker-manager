<?php

namespace App\Exception\MachineProvider;

use App\Exception\StackedExceptionInterface;
use App\Exception\UnrecoverableExceptionInterface;

interface AuthenticationExceptionInterface extends
    ExceptionInterface,
    UnrecoverableExceptionInterface,
    StackedExceptionInterface,
    HasMachineProviderInterface {}
