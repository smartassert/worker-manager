<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\MachineManager\DigitalOcean;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Model\DigitalOcean\RemoteMachine;
use App\Repository\MachineRepository;
use App\Services\MachineManager\DigitalOcean\MachineManager;
use App\Services\MachineNameFactory;
use App\Tests\AbstractBaseFunctionalTestCase;
use App\Tests\DataProvider\RemoteRequestThrowsExceptionDataProviderTrait;
use App\Tests\Proxy\DigitalOceanV2\Api\DropletApiProxy;
use App\Tests\Services\EntityRemover;
use DigitalOceanV2\Entity\Droplet as DropletEntity;

class DigitalOceanMachineManagerTest extends AbstractBaseFunctionalTestCase
{
    use RemoteRequestThrowsExceptionDataProviderTrait;

    private const MACHINE_ID = 'machine id';

    private MachineManager $machineManager;
    private Machine $machine;
    private string $machineName;
    private DropletApiProxy $dropletApiProxy;

    protected function setUp(): void
    {
        parent::setUp();

        $machineManager = self::getContainer()->get(MachineManager::class);
        \assert($machineManager instanceof MachineManager);
        $this->machineManager = $machineManager;

        $machineNameFactory = self::getContainer()->get(MachineNameFactory::class);
        \assert($machineNameFactory instanceof MachineNameFactory);
        $this->machineName = $machineNameFactory->create(self::MACHINE_ID);

        $dropletApiProxy = self::getContainer()->get(DropletApiProxy::class);
        \assert($dropletApiProxy instanceof DropletApiProxy);
        $this->dropletApiProxy = $dropletApiProxy;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
        }

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $this->machine = new Machine(self::MACHINE_ID);
        $this->machine->setState(MachineState::CREATE_RECEIVED);
        $machineRepository->add($this->machine);
    }

    public function testCreateSuccess(): void
    {
        $ipAddresses = ['10.0.0.1', '127.0.0.1'];

        $dropletData = [
            'networks' => (object) [
                'v4' => [
                    (object) [
                        'ip_address' => $ipAddresses[0],
                        'type' => 'public',
                    ],
                    (object) [
                        'ip_address' => $ipAddresses[1],
                        'type' => 'public',
                    ],
                ],
            ],
        ];

        $droplet = new DropletEntity($dropletData);
        $this->dropletApiProxy->prepareCreateCall($this->machineName, $droplet);

        $remoteMachine = $this->machineManager->create(self::MACHINE_ID, $this->machineName);

        self::assertEquals(new RemoteMachine($droplet), $remoteMachine);
    }

    public function testGetSuccess(): void
    {
        $ipAddresses = ['10.0.0.1', '127.0.0.1'];

        self::assertSame([], $this->machine->getIpAddresses());

        $dropletData = [
            'networks' => (object) [
                'v4' => [
                    (object) [
                        'ip_address' => $ipAddresses[0],
                        'type' => 'public',
                    ],
                    (object) [
                        'ip_address' => $ipAddresses[1],
                        'type' => 'public',
                    ],
                ],
            ],
        ];

        $expectedDropletEntity = new DropletEntity($dropletData);
        $this->dropletApiProxy->withGetAllCall($this->machineName, [$expectedDropletEntity]);

        $remoteMachine = $this->machineManager->get(self::MACHINE_ID, $this->machineName);

        self::assertEquals(new RemoteMachine($expectedDropletEntity), $remoteMachine);
    }

    public function testGetMachineNotFound(): void
    {
        $this->dropletApiProxy->withGetAllCall($this->machineName, []);

        $remoteMachine = $this->machineManager->get(self::MACHINE_ID, $this->machineName);

        self::assertNull($remoteMachine);
    }

    public function testRemoveSuccess(): void
    {
        $this->dropletApiProxy->withRemoveTaggedCall($this->machineName);
        $this->machineManager->remove(self::MACHINE_ID, $this->machineName);

        self::expectNotToPerformAssertions();
    }
}
