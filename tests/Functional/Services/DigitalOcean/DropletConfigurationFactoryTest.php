<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\DigitalOcean;

use App\Model\DigitalOcean\FooDropletConfiguration;
use App\Services\DigitalOcean\DropletConfigurationFactory;
use App\Tests\AbstractBaseFunctionalTest;

class DropletConfigurationFactoryTest extends AbstractBaseFunctionalTest
{
    private DropletConfigurationFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::$container->get(DropletConfigurationFactory::class);
        \assert($factory instanceof DropletConfigurationFactory);
        $this->factory = $factory;
    }

    public function testCreateHasDefaults(): void
    {
        $configuration = $this->factory->create();

        self::assertInstanceOf(FooDropletConfiguration::class, $configuration);

        self::assertSame([], $configuration->getNames());
        self::assertSame('lon1', $configuration->getRegion());
        self::assertSame('s-1vcpu-1gb', $configuration->getSize());
        self::assertSame('ubuntu-20-04-x64', $configuration->getImage());
        self::assertFalse($configuration->getBackups());
        self::assertFalse($configuration->getIpv6());
        self::assertFalse($configuration->getVpcUuid());
        self::assertSame([], $configuration->getSshKeys());
        self::assertSame('', $configuration->getUserData());
        self::assertFalse($configuration->getMonitoring());
        self::assertSame([], $configuration->getVolumes());
        self::assertSame(['worker'], $configuration->getTags());
    }
}
