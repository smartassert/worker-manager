<?php

namespace App\Services\ExceptionIdentifier;

use App\Services\MachineManager\DigitalOcean\Exception\EmptyDropletCollectionException;

class DigitalOceanExceptionIdentifier implements ExceptionIdentifierInterface
{
    public function isMachineNotFoundException(\Throwable $throwable): bool
    {
        return $throwable instanceof EmptyDropletCollectionException;
    }
}
