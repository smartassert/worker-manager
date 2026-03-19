<?php

namespace App\Exception\MachineProvider;

use App\Exception\UnrecoverableExceptionInterface;

interface ApiLimitExceptionInterface extends
    ExceptionInterface,
    UnrecoverableExceptionInterface,
    HasMachineProviderInterface
{
    public function getResetTimestamp(): int;
}
