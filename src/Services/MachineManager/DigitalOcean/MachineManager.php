<?php

namespace App\Services\MachineManager\DigitalOcean;

use App\Enum\MachineProvider;
use App\Exception\NoDigitalOceanClientException;
use App\Model\DigitalOcean\RemoteMachine;
use App\Model\RemoteMachineInterface;
use App\Services\MachineManager\DigitalOcean\Client\Client;
use App\Services\MachineManager\DigitalOcean\Exception\EmptyDropletCollectionException;
use App\Services\MachineManager\DigitalOcean\Exception\ErrorException;
use App\Services\MachineManager\DigitalOcean\Exception\InvalidEntityDataException;
use App\Services\MachineManager\DigitalOcean\Exception\MissingDropletException;
use App\Services\MachineManager\ProviderMachineManagerInterface;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\ExceptionInterface as VendorExceptionInterface;
use Psr\Http\Client\ClientExceptionInterface;
use SmartAssert\DigitalOceanDropletConfiguration\Factory;

readonly class MachineManager implements ProviderMachineManagerInterface
{
    public function __construct(
        private Factory $dropletConfigurationFactory,
        private ClientPool $clientPool,
        private Client $digitalOceanClient,
    ) {
    }

    /**
     * @param non-empty-string $machineId
     *
     * @throws VendorExceptionInterface
     * @throws NoDigitalOceanClientException
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

        $dropletEntity = $this->clientPool->droplet()->create(
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
     * @throws ClientExceptionInterface
     * @throws ErrorException
     * @throws MissingDropletException
     * @throws NoDigitalOceanClientException
     */
    public function remove(string $machineId, string $name): void
    {
        $this->digitalOceanClient->deleteDroplet($name);
    }

    /**
     * @param non-empty-string $machineId
     * @param non-empty-string $name
     *
     * @throws InvalidEntityDataException
     * @throws NoDigitalOceanClientException
     * @throws ClientExceptionInterface
     * @throws ErrorException
     */
    public function get(string $machineId, string $name): ?RemoteMachineInterface
    {
        try {
            return new RemoteMachine($this->digitalOceanClient->getDroplet($name));
        } catch (EmptyDropletCollectionException) {
            return null;
        }
    }

    public function supports(MachineProvider $provider): bool
    {
        return MachineProvider::DIGITALOCEAN === $provider;
    }
}
