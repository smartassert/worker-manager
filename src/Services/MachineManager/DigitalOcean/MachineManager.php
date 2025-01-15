<?php

namespace App\Services\MachineManager\DigitalOcean;

use App\Enum\MachineProvider;
use App\Model\DigitalOcean\RemoteMachine;
use App\Model\RemoteMachineInterface;
use App\Services\MachineManager\DigitalOcean\Client\Client;
use App\Services\MachineManager\DigitalOcean\Exception\ApiLimitExceededException;
use App\Services\MachineManager\DigitalOcean\Exception\AuthenticationException;
use App\Services\MachineManager\DigitalOcean\Exception\EmptyDropletCollectionException;
use App\Services\MachineManager\DigitalOcean\Exception\ErrorException;
use App\Services\MachineManager\DigitalOcean\Exception\InvalidEntityDataException;
use App\Services\MachineManager\DigitalOcean\Exception\MissingDropletException;
use App\Services\MachineManager\ProviderMachineManagerInterface;
use Psr\Http\Client\ClientExceptionInterface;
use SmartAssert\DigitalOceanDropletConfiguration\Factory;

readonly class MachineManager implements ProviderMachineManagerInterface
{
    public function __construct(
        private Factory $dropletConfigurationFactory,
        private Client $digitalOceanClient,
    ) {
    }

    /**
     * @param non-empty-string $machineId
     *
     * @throws ClientExceptionInterface
     * @throws EmptyDropletCollectionException
     * @throws ErrorException
     * @throws InvalidEntityDataException
     * @throws AuthenticationException
     * @throws ApiLimitExceededException
     */
    public function create(string $machineId, string $name): RemoteMachineInterface
    {
        $configuration = $this->dropletConfigurationFactory->create();
        $configuration = $configuration->withNames([$name]);
        $configuration = $configuration->addTags([$name]);

        $droplet = $this->digitalOceanClient->createDroplet(
            $name,
            $configuration->getRegion(),
            $configuration->getSize(),
            $configuration->getImage(),
            $configuration->getTags(),
            $configuration->getUserData()
        );

        return new RemoteMachine($droplet);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws ErrorException
     * @throws MissingDropletException
     * @throws AuthenticationException
     * @throws ApiLimitExceededException
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
     * @throws AuthenticationException
     * @throws ClientExceptionInterface
     * @throws ErrorException
     * @throws ApiLimitExceededException
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
