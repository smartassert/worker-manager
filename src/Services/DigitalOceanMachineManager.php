<?php

namespace App\Services;

use App\Exception\MachineProvider\ExceptionInterface;
use App\Model\DigitalOcean\RemoteMachine;
use App\Model\MachineActionInterface;
use App\Model\ProviderInterface;
use App\Model\RemoteMachineInterface;
use App\Services\ExceptionFactory\MachineProvider\DigitalOceanExceptionFactory;
use DigitalOceanV2\Api\Droplet as DropletApi;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\ExceptionInterface as VendorExceptionInterface;
use SmartAssert\DigitalOceanDropletConfiguration\Factory;

class DigitalOceanMachineManager implements ProviderMachineManagerInterface
{
    public function __construct(
        private DropletApi $dropletApi,
        private DigitalOceanExceptionFactory $exceptionFactory,
        private Factory $dropletConfigurationFactory,
    ) {
    }

    /**
     * @return ProviderInterface::NAME_* $type
     */
    public function getType(): string
    {
        return ProviderInterface::NAME_DIGITALOCEAN;
    }

    /**
     * @throws ExceptionInterface
     */
    public function create(string $machineId, string $name): RemoteMachineInterface
    {
        $configuration = $this->dropletConfigurationFactory->create();
        $configuration = $configuration->withNames([$name]);
        $configuration = $configuration->addTags([$name]);

        try {
            $namesValue = $configuration->getNames();
            $namesSize = count($namesValue);
            if (0 === $namesSize) {
                $namesValue = '';
            } elseif (1 === $namesSize) {
                $namesValue = $namesValue[0];
            }

            $dropletEntity = $this->dropletApi->create(
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
        } catch (VendorExceptionInterface $exception) {
            throw $this->exceptionFactory->create(
                $machineId,
                MachineActionInterface::ACTION_CREATE,
                $exception
            );
        }

        return new RemoteMachine(
            $dropletEntity instanceof DropletEntity ? $dropletEntity : new DropletEntity([])
        );
    }

    /**
     * @throws ExceptionInterface
     */
    public function remove(string $machineId, string $name): void
    {
        try {
            $this->dropletApi->removeTagged($name);
        } catch (VendorExceptionInterface $exception) {
            throw $this->exceptionFactory->create(
                $machineId,
                MachineActionInterface::ACTION_DELETE,
                $exception
            );
        }
    }

    /**
     * @throws ExceptionInterface
     */
    public function get(string $machineId, string $name): ?RemoteMachineInterface
    {
        try {
            $droplets = $this->dropletApi->getAll($name);
        } catch (VendorExceptionInterface $exception) {
            throw $this->exceptionFactory->create(
                $machineId,
                MachineActionInterface::ACTION_GET,
                $exception
            );
        }

        return 1 === count($droplets)
            ? new RemoteMachine($droplets[0])
            : null;
    }
}
