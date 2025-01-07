<?php

namespace App\Services\MachineManager\DigitalOcean\Entity;

readonly class Network
{
    /**
     * @param non-empty-string $ipAddress
     */
    public function __construct(
        public string $ipAddress,
        public bool $isPublic,
    ) {
    }
}
