<?php

declare(strict_types=1);

namespace App\Tests\Proxy\DigitalOceanV2\Api;

use DigitalOceanV2\Api\Droplet;
use DigitalOceanV2\Client;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\RuntimeException;
use Mockery\MockInterface;
use SmartAssert\DigitalOceanDropletConfiguration\Configuration;
use SmartAssert\DigitalOceanDropletConfiguration\Factory;

class DropletApiProxy extends Droplet
{
    private Droplet $mock;

    public function __construct(
        private Factory $digitalOceanDropletConfigurationFactory,
    ) {
        parent::__construct(\Mockery::mock(Client::class));

        $this->mock = \Mockery::mock(Droplet::class);
    }

    public function prepareGetByIdZeroCall(): self
    {
        return $this->withGetByIdCall(0, new RuntimeException('The resource you requested could not be found.', 404));
    }

    public function withGetByIdCall(int $id, object $outcome): self
    {
        return $this->withCall('getById', [$id], $outcome);
    }

    public function getById(int $id): DropletEntity
    {
        return $this->mock->getById($id);
    }

    public function withCreateCall(string $name, Configuration $dropletConfiguration, object $outcome): self
    {
        return $this->withCall(
            'create',
            [
                $name,
                $dropletConfiguration->getRegion(),
                $dropletConfiguration->getSize(),
                $dropletConfiguration->getImage(),
                $dropletConfiguration->getBackups(),
                $dropletConfiguration->getIpv6(),
                $dropletConfiguration->getVpcUuid(),
                $dropletConfiguration->getSshKeys(),
                $dropletConfiguration->getUserData(),
                $dropletConfiguration->getMonitoring(),
                $dropletConfiguration->getVolumes(),
                $dropletConfiguration->getTags(),
                false,
            ],
            $outcome
        );
    }

    /**
     * @param string|string[] $names
     * @param int[]           $sshKeys
     * @param int[]           $volumes
     * @param int[]           $tags
     * @param mixed           $image
     * @param mixed           $vpcUuid
     */
    public function create(
        $names,
        string $region,
        string $size,
        $image,
        bool $backups = false,
        bool $ipv6 = false,
        $vpcUuid = false,
        array $sshKeys = [],
        string $userData = '',
        bool $monitoring = true,
        array $volumes = [],
        array $tags = [],
        bool $disableAgent = false,
    ): DropletEntity {
        if (false === is_string($image)) {
            throw new \RuntimeException('image is not a string');
        }

        if (false === is_string($vpcUuid) && false === is_bool($vpcUuid)) {
            throw new \RuntimeException('vpcUuid is not a string');
        }

        $droplet = $this->mock->create(
            $names,
            $region,
            $size,
            $image,
            $backups,
            $ipv6,
            $vpcUuid,
            $sshKeys,
            $userData,
            $monitoring,
            $volumes,
            $tags,
            $disableAgent
        );

        if (false === $droplet instanceof DropletEntity) {
            throw new \RuntimeException('Created object not of type ' . DropletEntity::class);
        }

        return $droplet;
    }

    public function withRemoveTaggedCall(string $tag, ?\Exception $exception = null): self
    {
        return $this->withCall('removeTagged', [$tag], $exception);
    }

    public function removeTagged(string $tag): void
    {
        $this->mock->removeTagged($tag);
    }

    /**
     * @param null|DropletEntity[]|\Exception $outcome
     */
    public function withGetAllCall(string $name, array|\Exception|null $outcome): self
    {
        return $this->withCall('getAll', [$name], $outcome);
    }

    /**
     * @return DropletEntity[]
     */
    public function getAll(?string $tag = null): array
    {
        return $this->mock->getAll($tag);
    }

    public function prepareCreateCall(
        string $machineName,
        DropletEntity|\Throwable $outcome
    ): void {
        $dropletConfiguration = $this->createDropletConfiguration($machineName);

        $this->withCreateCall($machineName, $dropletConfiguration, $outcome);
    }

    private function createDropletConfiguration(string $name): Configuration
    {
        $configuration = $this->digitalOceanDropletConfigurationFactory->create();
        $configuration = $configuration->withNames([$name]);

        return $configuration->addTags([$name]);
    }

    /**
     * @param array<mixed> $args
     */
    private function withCall(string $methodName, array $args, mixed $outcome): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $expectation = $this->mock
            ->shouldReceive($methodName)
            ->withArgs($args)
        ;

        if ($outcome instanceof \Exception) {
            $expectation->andThrow($outcome);
        } elseif (null !== $outcome) {
            $expectation->andReturn($outcome);
        }

        return $this;
    }
}
