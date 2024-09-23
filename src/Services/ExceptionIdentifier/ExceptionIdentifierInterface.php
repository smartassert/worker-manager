<?php

namespace App\Services\ExceptionIdentifier;

interface ExceptionIdentifierInterface
{
    public function isMachineNotFoundException(\Throwable $throwable): bool;
}
