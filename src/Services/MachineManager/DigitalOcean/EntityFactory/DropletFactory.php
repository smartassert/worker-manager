<?php

namespace App\Services\MachineManager\DigitalOcean\EntityFactory;

use App\Services\MachineManager\DigitalOcean\Entity\Droplet;
use App\Services\MachineManager\DigitalOcean\Exception\InvalidEntityDataException;

readonly class DropletFactory
{
    public function __construct(
        private NetworkCollectionFactory $networkCollectionFactory,
    ) {
    }

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidEntityDataException
     */
    public function createFromSingleCollection(array $data): Droplet
    {
        $dropletsData = $data['droplets'] ?? null;
        $dropletsData = is_array($dropletsData) ? $dropletsData : null;

        if (
            null === $dropletsData
            || 1 !== count($dropletsData)
            || !is_array($dropletsData[0])
        ) {
            throw new InvalidEntityDataException('droplet_as_collection', $data);
        }

        return $this->create($dropletsData[0]);
    }

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidEntityDataException
     */
    private function create(array $data): Droplet
    {
        $id = $data['id'] ?? null;
        $id = (is_int($id) && $id > 0) ? $id : null;

        if (null === $id) {
            throw new InvalidEntityDataException('droplet', $data);
        }

        $status = $data['status'] ?? null;
        $status = (is_string($status) && '' !== $status) ? $status : null;

        if (null === $status) {
            throw new InvalidEntityDataException('droplet', $data);
        }

        $networksData = $data['networks'] ?? null;
        $networksData = is_array($networksData) ? $networksData : [];

        $networkCollection = $this->networkCollectionFactory->create($networksData);

        return new Droplet($id, $status, $networkCollection);
    }
}
