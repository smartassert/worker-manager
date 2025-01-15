<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\MachineManager\DigitalOcean;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Model\DigitalOcean\RemoteMachine;
use App\Repository\MachineRepository;
use App\Services\MachineManager\DigitalOcean\Entity\Droplet;
use App\Services\MachineManager\DigitalOcean\Entity\Network;
use App\Services\MachineManager\DigitalOcean\Entity\NetworkCollection;
use App\Services\MachineManager\DigitalOcean\MachineManager;
use App\Services\MachineNameFactory;
use App\Tests\AbstractBaseFunctionalTestCase;
use App\Tests\Services\EntityRemover;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

class DigitalOceanMachineManagerTest extends AbstractBaseFunctionalTestCase
{
    private const MACHINE_ID = 'machine id';

    private MachineManager $machineManager;
    private Machine $machine;

    /**
     * @var non-empty-string
     */
    private string $machineName;

    protected function setUp(): void
    {
        parent::setUp();

        $machineManager = self::getContainer()->get(MachineManager::class);
        \assert($machineManager instanceof MachineManager);
        $this->machineManager = $machineManager;

        $machineNameFactory = self::getContainer()->get(MachineNameFactory::class);
        \assert($machineNameFactory instanceof MachineNameFactory);
        $this->machineName = $machineNameFactory->create(self::MACHINE_ID);

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

        $dropletId = rand(1, PHP_INT_MAX);
        $dropletStatus = RemoteMachine::STATE_NEW;

        $expectedDropletEntity = new Droplet(
            $dropletId,
            RemoteMachine::STATE_NEW,
            new NetworkCollection([
                new Network($ipAddresses[0], true, 4),
                new Network($ipAddresses[1], true, 4),
            ])
        );

        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $mockHandler->append(new Response(
            200,
            [
                'Content-Type' => 'application/json'
            ],
            (string) json_encode([
                'droplets' => [
                    [
                        'id' => $dropletId,
                        'status' => $dropletStatus,
                        'networks' => [
                            'v4' => [
                                [
                                    'ip_address' => $ipAddresses[0],
                                    'type' => 'public',
                                ],
                                [
                                    'ip_address' => $ipAddresses[1],
                                    'type' => 'public',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ));

        $remoteMachine = $this->machineManager->create(self::MACHINE_ID, $this->machineName);

        self::assertEquals(new RemoteMachine($expectedDropletEntity), $remoteMachine);
    }

    public function testGetSuccess(): void
    {
        self::assertSame([], $this->machine->getIpAddresses());

        $ipAddresses = ['10.0.0.1', '127.0.0.1'];

        $dropletId = rand(1, PHP_INT_MAX);
        $dropletStatus = RemoteMachine::STATE_NEW;

        $expectedDropletEntity = new Droplet(
            $dropletId,
            RemoteMachine::STATE_NEW,
            new NetworkCollection([
                new Network($ipAddresses[0], true, 4),
                new Network($ipAddresses[1], true, 4),
            ])
        );

        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $mockHandler->append(new Response(
            200,
            [
                'Content-Type' => 'application/json'
            ],
            (string) json_encode([
                'droplets' => [
                    [
                        'id' => $dropletId,
                        'status' => $dropletStatus,
                        'networks' => [
                            'v4' => [
                                [
                                    'ip_address' => $ipAddresses[0],
                                    'type' => 'public',
                                ],
                                [
                                    'ip_address' => $ipAddresses[1],
                                    'type' => 'public',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ));

        $remoteMachine = $this->machineManager->get(self::MACHINE_ID, $this->machineName);

        self::assertEquals(new RemoteMachine($expectedDropletEntity), $remoteMachine);
    }

    public function testGetMachineNotFound(): void
    {
        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $mockHandler->append(new Response(
            200,
            [
                'Content-Type' => 'application/json'
            ],
            (string) json_encode([
                'droplets' => [],
            ]),
        ));

        $remoteMachine = $this->machineManager->get(self::MACHINE_ID, $this->machineName);

        self::assertNull($remoteMachine);
    }

    public function testRemoveSuccess(): void
    {
        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);
        $mockHandler->append(new Response(204));

        $this->machineManager->remove(self::MACHINE_ID, $this->machineName);

        self::expectNotToPerformAssertions();
    }
}
