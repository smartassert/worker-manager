<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Services\MachineNameFactory;
use App\Tests\AbstractBaseFunctionalTestCase;

class MachineNameFactoryTest extends AbstractBaseFunctionalTestCase
{
    private MachineNameFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(MachineNameFactory::class);
        \assert($factory instanceof MachineNameFactory);
        $this->factory = $factory;
    }

    public function testCreate(): void
    {
        $machineId = 'machine_id';

        self::assertSame('test-worker-machine_id', $this->factory->create($machineId));
    }
}
