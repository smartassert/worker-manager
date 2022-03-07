<?php

declare(strict_types=1);

namespace App\Tests\Proxy\DigitalOceanV2\Api;

use DigitalOceanV2\Api\Droplet;
use DigitalOceanV2\Client;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
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

    public function withGetByIdCall(int $id, object $outcome): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $expectation = $this->mock
            ->shouldReceive('getById')
            ->with($id)
        ;

        if ($outcome instanceof \Exception) {
            $expectation->andThrow($outcome)
            ;
        } else {
            $expectation->andReturn($outcome);
        }

        return $this;
    }

    public function getById(int $id): DropletEntity
    {
        return $this->mock->getById($id);
    }

    /**
     * @param int[]    $sshKeys
     * @param string[] $volumes
     * @param string[] $tags
     */
    public function withCreateCall(
        string $name,
        string $region,
        string $size,
        string $image,
        bool $backups,
        bool $ipv6,
        bool | string $vpcUuid,
        array $sshKeys,
        string $userData,
        bool $monitoring,
        array $volumes,
        array $tags,
        object $outcome
    ): self {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $expectation = $this->mock
            ->shouldReceive('create')
            ->with(
                $name,
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
                $tags
            )
        ;

        if ($outcome instanceof \Exception) {
            $expectation->andThrow($outcome)
            ;
        } else {
            $expectation->andReturn($outcome);
        }

        return $this;
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
        array $tags = []
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
            $tags
        );

        if (false === $droplet instanceof DropletEntity) {
            throw new \RuntimeException('Created object not of type ' . DropletEntity::class);
        }

        return $droplet;
    }

    public function withRemoveTaggedCall(string $tag, ?\Exception $exception = null): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $expectation = $this->mock
            ->shouldReceive('removeTagged')
            ->with($tag)
        ;

        if ($exception instanceof \Exception) {
            $expectation->andThrow($exception);
        }

        return $this;
    }

    public function removeTagged(string $tag): void
    {
        $this->mock->removeTagged($tag);
    }

    /**
     * @param null|DropletEntity[]|\Exception $outcome
     */
    public function withGetAllCall(string $name, null | \Exception | array $outcome): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $expectation = $this->mock
            ->shouldReceive('getAll')
            ->with($name)
        ;

        if ($outcome instanceof \Exception) {
            $expectation->andThrow($outcome);
        } else {
            $expectation->andReturn($outcome);
        }

        return $this;
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
        DropletEntity | \Exception $outcome
    ): void {
        $dropletConfiguration = $this->createDropletConfiguration($machineName);

        $this->withCreateCall(
            $machineName,
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
            $outcome,
        );
    }

    private function createDropletConfiguration(string $name): Configuration
    {
        $configuration = $this->digitalOceanDropletConfigurationFactory->create();
        $configuration = $configuration->withNames([$name]);

        return $configuration->addTags([$name]);
    }
}
