<?php

namespace App\Services\MachineManager\DigitalOcean\EntityFactory;

use App\Services\MachineManager\DigitalOcean\Entity\DropletCollection;
use App\Services\MachineManager\DigitalOcean\Exception\InvalidEntityDataException;

readonly class DropletCollectionFactory
{
    public function __construct(
        private DropletFactory $dropletFactory,
    ) {
    }

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidEntityDataException
     */
    public function create(array $data): DropletCollection
    {
        $droplets = [];

        $dropletsData = $data['droplets'] ?? [];
        $dropletsData = is_array($dropletsData) ? $dropletsData : [];

        foreach ($dropletsData as $dropletData) {
            if (is_array($dropletData)) {
                $droplets[] = $this->dropletFactory->create($dropletData);
            }
        }

        return new DropletCollection($droplets);
    }
}
