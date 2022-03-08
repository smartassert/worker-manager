<?php

namespace App\Services\ServiceStatusInspector;

use DigitalOceanV2\Api\Droplet;
use DigitalOceanV2\Exception\RuntimeException;

class DigitalOceanMachineProviderInspector
{
    public const DROPLET_ID = 123456;

    public function __construct(
        private Droplet $dropletApi
    ) {
    }

    public function __invoke(): void
    {
        try {
            $this->dropletApi->getById(self::DROPLET_ID);
        } catch (RuntimeException $runtimeException) {
            if (404 !== $runtimeException->getCode()) {
                throw $runtimeException;
            }
        }
    }
}
