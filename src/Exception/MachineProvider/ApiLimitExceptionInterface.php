<?php

namespace App\Exception\MachineProvider;

use App\Exception\UnrecoverableExceptionInterface;

interface ApiLimitExceptionInterface extends ExceptionInterface, UnrecoverableExceptionInterface
{
    public function getResetTimestamp(): int;
}
