<?php

namespace App\Services\ExceptionIdentifier;

use DigitalOceanV2\Exception\ResourceNotFoundException;

class DigitalOceanExceptionIdentifier implements ExceptionIdentifierInterface
{
    public function isMachineNotFoundException(\Throwable $throwable): bool
    {
        return $throwable instanceof ResourceNotFoundException;
    }
}
