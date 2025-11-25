<?php

namespace App\Services\MachineManager\DigitalOcean\EntityFactory;

use App\Services\MachineManager\DigitalOcean\Entity\Network;
use App\Services\MachineManager\DigitalOcean\Entity\NetworkCollection;
use App\Services\MachineManager\DigitalOcean\Exception\InvalidEntityDataException;

readonly class NetworkCollectionFactory
{
    public function __construct(
        private NetworkFactory $networkFactory,
    ) {}

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidEntityDataException
     */
    public function create(array $data): NetworkCollection
    {
        $networks = [];

        $v4Data = $data['v4'] ?? [];
        $networks = array_merge($networks, $this->createFromSet(is_array($v4Data) ? $v4Data : [], 4));

        $v6Data = $data['v6'] ?? [];
        $networks = array_merge($networks, $this->createFromSet(is_array($v6Data) ? $v6Data : [], 6));

        return new NetworkCollection($networks);
    }

    /**
     * @param array<mixed> $data
     * @param positive-int $ipVersion
     *
     * @return Network[]
     *
     * @throws InvalidEntityDataException
     */
    private function createFromSet(array $data, int $ipVersion): array
    {
        $networks = [];

        foreach ($data as $networkData) {
            if (is_array($networkData)) {
                $networks[] = $this->networkFactory->create($networkData, $ipVersion);
            }
        }

        return $networks;
    }
}
