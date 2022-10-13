<?php

namespace App\Services\ServiceStatusInspector;

use DigitalOceanV2\Client;
use DigitalOceanV2\Exception\RuntimeException;
use SmartAssert\ServiceStatusInspector\ComponentStatusInspectorInterface;

class DigitalOceanMachineProviderInspector implements ComponentStatusInspectorInterface
{
    public const DROPLET_ID = 123456;
    public const DEFAULT_IDENTIFIER = 'machine_provider_digital_ocean';

    public function __construct(
        private readonly Client $digitalOceanClient,
        private readonly string $identifier = self::DEFAULT_IDENTIFIER,
    ) {
    }

    public function getStatus(): bool
    {
        try {
            $this->digitalOceanClient->droplet()->getById(self::DROPLET_ID);
        } catch (RuntimeException $runtimeException) {
            if (404 !== $runtimeException->getCode()) {
                throw $runtimeException;
            }
        }

        return true;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
