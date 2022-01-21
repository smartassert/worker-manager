<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Exception\MachineNotFindableException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Model\DigitalOcean\RemoteMachine;
use App\Model\MachineActionInterface;
use App\Services\RemoteMachineFinder;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

class RemoteMachineFinderTest extends AbstractBaseFunctionalTest
{
    private const MACHINE_ID = 'machine id';

    private RemoteMachineFinder $finder;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $machineManager = self::getContainer()->get(RemoteMachineFinder::class);
        \assert($machineManager instanceof RemoteMachineFinder);
        $this->finder = $machineManager;

        $mockHandler = self::getContainer()->get(MockHandler::class);
        if ($mockHandler instanceof MockHandler) {
            $this->mockHandler = $mockHandler;
        }
    }

    public function testFindSuccess(): void
    {
        $dropletEntity = new DropletEntity([
            'id' => 123,
            'status' => RemoteMachine::STATE_NEW,
        ]);

        $this->mockHandler->append(HttpResponseFactory::fromDropletEntityCollection([$dropletEntity]));

        $remoteMachine = $this->finder->find(self::MACHINE_ID);

        self::assertEquals(new RemoteMachine($dropletEntity), $remoteMachine);
    }

    public function testFindMachineNotFindable(): void
    {
        $this->mockHandler->append(new Response(503));

        $expectedExceptionStack = [
            new HttpException(
                self::MACHINE_ID,
                MachineActionInterface::ACTION_GET,
                new RuntimeException('Service Unavailable', 503)
            ),
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
        $this->mockHandler->append(HttpResponseFactory::fromDropletEntityCollection([]));

        self::assertNull($this->finder->find(self::MACHINE_ID));
    }
}
