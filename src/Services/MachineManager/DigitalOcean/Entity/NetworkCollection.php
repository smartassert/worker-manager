<?php

namespace App\Services\MachineManager\DigitalOcean\Entity;

readonly class NetworkCollection
{
    /**
     * @param non-empty-array<Network> $networks
     */
    public function __construct(
        public array $networks,
    ) {
    }

    /**
     * @return non-empty-string[]
     */
    public function getPublicIpv4Addresses(): array
    {
        $ipAddresses = [];

        foreach ($this->networks as $network) {
            $ipAddress = $network->getPublicIpv4Address();

            if (is_string($ipAddress)) {
                $ipAddresses[] = $ipAddress;
            }
        }

        return $ipAddresses;
    }

    /**
     * @return ?non-empty-string
     */
    public function getFirstPublicIpv4Address(): ?string
    {
        return $this->getPublicIpv4Addresses()[0] ?? null;
    }
}
