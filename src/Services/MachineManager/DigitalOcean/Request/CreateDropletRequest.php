<?php

namespace App\Services\MachineManager\DigitalOcean\Request;

use SmartAssert\DigitalOceanDropletConfiguration\Configuration;

readonly class CreateDropletRequest implements RequestInterface
{
    public function __construct(
        private Configuration $configuration,
    ) {}

    public function getMethod(): string
    {
        return 'POST';
    }

    public function getUrl(): string
    {
        return '/droplets';
    }

    public function getPayload(): ?array
    {
        return $this->configuration->jsonSerialize();
    }
}
