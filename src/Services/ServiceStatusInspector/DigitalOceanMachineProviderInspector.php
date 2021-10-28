<?php

namespace App\Services\ServiceStatusInspector;

use DigitalOceanV2\Api\Droplet;
use DigitalOceanV2\Exception\RuntimeException;

class DigitalOceanMachineProviderInspector
{
    public function __construct(
        private Droplet $dropletApi
    ) {
    }

    public function __invoke(): void
    {
        try {
            $this->dropletApi->getById(123456);
        } catch (RuntimeException $runtimeException) {
            if (404 !== $runtimeException->getCode()) {
                throw $runtimeException;
            }
        }
    }
}
