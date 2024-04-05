<?php

namespace App\Services;

use App\Model\DigitalOcean\RemoteMachine;
use App\Model\RemoteMachineInterface;
use DigitalOceanV2\Client;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\ExceptionInterface as VendorExceptionInterface;
use SmartAssert\DigitalOceanDropletConfiguration\Factory;

readonly class DigitalOceanMachineManager implements ProviderMachineManagerInterface
{
    public function __construct(
        private Client $digitalOceanClient,
        private Factory $dropletConfigurationFactory,
    ) {
    }

    public function getType(): string
    {
        return RemoteMachine::TYPE;
    }

    /**
     * @param non-empty-string $machineId
     *
     * @throws VendorExceptionInterface
     */
    public function create(string $machineId, string $name): RemoteMachineInterface
    {
        $configuration = $this->dropletConfigurationFactory->create();
        $configuration = $configuration->withNames([$name]);
        $configuration = $configuration->addTags([$name]);

        $namesValue = $configuration->getNames();
        $namesSize = count($namesValue);
        if (0 === $namesSize) {
            $namesValue = '';
        } elseif (1 === $namesSize) {
            $namesValue = $namesValue[0];
        }

        $dropletEntity = $this->digitalOceanClient->droplet()->create(
            $namesValue,
            $configuration->getRegion(),
            $configuration->getSize(),
            $configuration->getImage(),
            $configuration->getBackups(),
            $configuration->getIpv6(),
            $configuration->getVpcUuid(),
            $configuration->getSshKeys(),
            $configuration->getUserData(),
            $configuration->getMonitoring(),
            $configuration->getVolumes(),
            $configuration->getTags()
        );

        return new RemoteMachine(
            $dropletEntity instanceof DropletEntity ? $dropletEntity : new DropletEntity([])
        );
    }

    /**
     * @param non-empty-string $machineId
     *
     * @throws VendorExceptionInterface
     */
    public function remove(string $machineId, string $name): void
    {
        $this->digitalOceanClient->droplet()->removeTagged($name);
    }

    /**
     * @param non-empty-string $machineId
     *
     * @throws VendorExceptionInterface
     */
    public function get(string $machineId, string $name): ?RemoteMachineInterface
    {
        $droplets = $this->digitalOceanClient->droplet()->getAll($name);

        return 1 === count($droplets) ? new RemoteMachine($droplets[0]) : null;
    }
}
