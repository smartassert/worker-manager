<?php

namespace App\Services\MachineManager\DigitalOcean\EntityFactory;

use App\Services\MachineManager\DigitalOcean\Entity\Network;
use App\Services\MachineManager\DigitalOcean\Exception\InvalidEntityDataException;

readonly class NetworkFactory
{
    /**
     * @param array<mixed> $data
     *
     * @throws InvalidEntityDataException
     */
    public function create(array $data): Network
    {
        $ipAddress = $data['ip_address'] ?? null;
        $ipAddress = (is_string($ipAddress) && '' !== $ipAddress) ? $ipAddress : null;

        if (null === $ipAddress) {
            throw new InvalidEntityDataException('network', $data);
        }

        $isPublic = $data['is_public'] ?? null;
        $isPublic = is_bool($isPublic) ? $isPublic : null;

        if (null === $isPublic) {
            throw new InvalidEntityDataException('network', $data);
        }

        return new Network($ipAddress, $isPublic);
    }
}
