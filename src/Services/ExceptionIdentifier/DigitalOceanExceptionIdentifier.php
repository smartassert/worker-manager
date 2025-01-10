<?php

namespace App\Services\ExceptionIdentifier;

use App\Services\MachineManager\DigitalOcean\Exception\EmptyDropletCollectionException;
use DigitalOceanV2\Exception\ResourceNotFoundException;

class DigitalOceanExceptionIdentifier implements ExceptionIdentifierInterface
{
    public function isMachineNotFoundException(\Throwable $throwable): bool
    {
        return $throwable instanceof ResourceNotFoundException || $throwable instanceof EmptyDropletCollectionException;
    }
}
