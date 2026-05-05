<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\MachineManager\DigitalOcean;

use App\Tests\AbstractBaseFunctionalTestCase;
use SmartAssert\DigitalOceanDropletConfiguration\Factory;

class ConfigurationFactoryTest extends AbstractBaseFunctionalTestCase
{
    public function testFoo(): void
    {
        $expectedSshKeyId = self::getContainer()->getParameter('worker_ssh_key_id');
        self::assertIsInt($expectedSshKeyId);

        $factory = self::getContainer()->get(Factory::class);
        \assert($factory instanceof Factory);

        $configuration = $factory->create();
        self::assertSame([$expectedSshKeyId], $configuration->getSshKeys());
    }
}
