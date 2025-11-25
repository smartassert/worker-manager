<?php

namespace App\Services\MachineManager\DigitalOcean\Request;

readonly class RemoveDropletRequest implements RequestInterface
{
    public function __construct(
        private string $name,
    ) {}

    public function getMethod(): string
    {
        return 'DELETE';
    }

    public function getUrl(): string
    {
        return sprintf('/droplets?tag_name=%s', $this->name);
    }

    public function getPayload(): null
    {
        return null;
    }
}
