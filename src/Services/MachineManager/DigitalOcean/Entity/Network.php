<?php

namespace App\Services\MachineManager\DigitalOcean\Entity;

readonly class Network
{
    /**
     * @param non-empty-string $ipAddress
     * @param positive-int     $ipVersion
     */
    public function __construct(
        public string $ipAddress,
        public bool $isPublic,
        public int $ipVersion,
    ) {}

    /**
     * @return ?non-empty-string
     */
    public function getPublicIpv4Address(): ?string
    {
        if (false === $this->isPublic || 4 !== $this->ipVersion) {
            return null;
        }

        return $this->ipAddress;
    }
}
