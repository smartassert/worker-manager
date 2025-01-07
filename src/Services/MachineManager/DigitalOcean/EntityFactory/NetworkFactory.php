<?php

namespace App\Services\MachineManager\DigitalOcean\EntityFactory;

use App\Services\MachineManager\DigitalOcean\Entity\Network;
use App\Services\MachineManager\DigitalOcean\Exception\InvalidEntityDataException;

readonly class NetworkFactory
{
    /**
     * @param array<mixed> $data
     * @param positive-int $ipVersion
     *
     * @throws InvalidEntityDataException
     */
    public function create(array $data, int $ipVersion): Network
    {
        $ipAddress = $data['ip_address'] ?? null;
        $ipAddress = (is_string($ipAddress) && '' !== $ipAddress) ? $ipAddress : null;

        if (null === $ipAddress) {
            throw new InvalidEntityDataException('network', $data);
        }

        $isPublic = 'public' === ($data['type'] ?? null);

        return new Network($ipAddress, $isPublic, $ipVersion);
    }
}
