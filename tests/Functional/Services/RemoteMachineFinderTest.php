<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Enum\MachineAction;
use App\Exception\MachineNotFindableException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Model\DigitalOcean\RemoteMachine;
use App\Services\MachineNameFactory;
use App\Services\RemoteMachineFinder;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Proxy\DigitalOceanV2\Api\DropletApiProxy;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\RuntimeException;

class RemoteMachineFinderTest extends AbstractBaseFunctionalTest
{
    private const MACHINE_ID = 'machine id';

    private RemoteMachineFinder $finder;
    private DropletApiProxy $dropletApiProxy;
    private string $machineName;

    protected function setUp(): void
    {
        parent::setUp();

        $machineManager = self::getContainer()->get(RemoteMachineFinder::class);
        \assert($machineManager instanceof RemoteMachineFinder);
        $this->finder = $machineManager;

        $dropletApiProxy = self::getContainer()->get(DropletApiProxy::class);
        \assert($dropletApiProxy instanceof DropletApiProxy);
        $this->dropletApiProxy = $dropletApiProxy;

        $machineNameFactory = self::getContainer()->get(MachineNameFactory::class);
        \assert($machineNameFactory instanceof MachineNameFactory);
        $this->machineName = $machineNameFactory->create(self::MACHINE_ID);
    }

    public function testFindSuccess(): void
    {
        $dropletEntity = new DropletEntity([
            'id' => 123,
            'status' => RemoteMachine::STATE_NEW,
        ]);

        $this->dropletApiProxy->withGetAllCall($this->machineName, [$dropletEntity]);

        $remoteMachine = $this->finder->find(self::MACHINE_ID);

        self::assertEquals(new RemoteMachine($dropletEntity), $remoteMachine);
    }

    public function testFindMachineNotFindable(): void
    {
        $http503Exception = new RuntimeException('Service Unavailable', 503);

        $this->dropletApiProxy->withGetAllCall($this->machineName, $http503Exception);

        $expectedExceptionStack = [
            new HttpException(self::MACHINE_ID, MachineAction::GET, $http503Exception),
        ];

        try {
            $this->finder->find(self::MACHINE_ID);
            self::fail(MachineNotFindableException::class . ' not thrown');
        } catch (MachineNotFindableException $machineNotFoundException) {
            self::assertEquals($expectedExceptionStack, $machineNotFoundException->getExceptionStack());
        }
    }

    public function testFindMachineDoesNotExist(): void
    {
        $this->dropletApiProxy->withGetAllCall($this->machineName, []);

        self::assertNull($this->finder->find(self::MACHINE_ID));
    }
}
