<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Machine;
use App\Exception\MachineProvider\Exception;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Model\DigitalOcean\RemoteMachine;
use App\Model\MachineActionInterface;
use App\Repository\MachineRepository;
use App\Services\DigitalOceanMachineManager;
use App\Services\ExceptionFactory\MachineProvider\DigitalOceanExceptionFactory;
use App\Services\MachineNameFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\DataProvider\RemoteRequestThrowsExceptionDataProviderTrait;
use App\Tests\Proxy\DigitalOceanV2\Api\DropletApiProxy;
use App\Tests\Services\EntityRemover;
use DigitalOceanV2\Client as DigitaloceanClient;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\ValidationFailedException;
use Psr\Http\Message\ResponseInterface;
use webignition\ObjectReflector\ObjectReflector;

class DigitalOceanMachineManagerTest extends AbstractBaseFunctionalTest
{
    use RemoteRequestThrowsExceptionDataProviderTrait;

    private const MACHINE_ID = 'machine id';

    private DigitalOceanMachineManager $machineManager;
    private Machine $machine;
    private string $machineName;
    private DropletApiProxy $dropletApiProxy;

    protected function setUp(): void
    {
        parent::setUp();

        $machineManager = self::getContainer()->get(DigitalOceanMachineManager::class);
        \assert($machineManager instanceof DigitalOceanMachineManager);
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

    /**
     * @dataProvider remoteRequestThrowsExceptionDataProvider
     *
     * @param class-string $expectedExceptionClass
     */
    public function testCreateThrowsException(
        \Exception $dropletApiException,
        ResponseInterface $apiResponse,
        string $expectedExceptionClass
    ): void {
        $this->doActionThrowsExceptionTest(
            function () use ($dropletApiException) {
                $this->dropletApiProxy->prepareCreateCall($this->machineName, $dropletApiException);
                $this->machineManager->create(self::MACHINE_ID, $this->machineName);
            },
            MachineActionInterface::ACTION_CREATE,
            $dropletApiException,
            $apiResponse,
            $expectedExceptionClass,
        );
    }

    public function testCreateThrowsDropletLimitException(): void
    {
        $dropletApiException = new ValidationFailedException(
            'creating this/these droplet(s) will exceed your droplet limit',
            422
        );

        $this->dropletApiProxy->prepareCreateCall($this->machineName, $dropletApiException);

        try {
            $this->machineManager->create(self::MACHINE_ID, $this->machineName);
            self::fail(ExceptionInterface::class . ' not thrown');
        } catch (ExceptionInterface $exception) {
            self::assertSame($dropletApiException, $exception->getRemoteException());
        }
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

    /**
     * @dataProvider remoteRequestThrowsExceptionDataProvider
     *
     * @param class-string $expectedExceptionClass
     */
    public function testGetThrowsException(
        \Exception $dropletApiException,
        ResponseInterface $apiResponse,
        string $expectedExceptionClass,
    ): void {
        $this->doActionThrowsExceptionTest(
            function () use ($dropletApiException) {
                $this->dropletApiProxy->withGetAllCall($this->machineName, $dropletApiException);
                $this->machineManager->get(self::MACHINE_ID, $this->machineName);
            },
            MachineActionInterface::ACTION_GET,
            $dropletApiException,
            $apiResponse,
            $expectedExceptionClass,
        );
    }

    public function testRemoveSuccess(): void
    {
        $this->dropletApiProxy->withRemoveTaggedCall($this->machineName);
        $this->machineManager->remove(self::MACHINE_ID, $this->machineName);

        self::expectNotToPerformAssertions();
    }

    /**
     * @dataProvider remoteRequestThrowsExceptionDataProvider
     *
     * @param class-string $expectedExceptionClass
     */
    public function testRemoveThrowsException(
        \Exception $dropletApiException,
        ResponseInterface $apiResponse,
        string $expectedExceptionClass,
    ): void {
        $this->doActionThrowsExceptionTest(
            function () use ($dropletApiException) {
                $this->dropletApiProxy->withRemoveTaggedCall($this->machineName, $dropletApiException);
                $this->machineManager->remove(self::MACHINE_ID, $this->machineName);
            },
            MachineActionInterface::ACTION_DELETE,
            $dropletApiException,
            $apiResponse,
            $expectedExceptionClass,
        );
    }

    /**
     * @param MachineActionInterface::ACTION_* $action
     * @param class-string                     $expectedExceptionClass
     */
    private function doActionThrowsExceptionTest(
        callable $callable,
        string $action,
        \Exception $dropletApiException,
        ResponseInterface $apiResponse,
        string $expectedExceptionClass,
    ): void {
        $digitalOceanClient = \Mockery::mock(DigitaloceanClient::class);
        $digitalOceanClient
            ->shouldReceive('getLastResponse')
            ->andReturn($apiResponse)
        ;

        $digitaloceanExceptionFactory = self::getContainer()->get(DigitalOceanExceptionFactory::class);
        \assert($digitaloceanExceptionFactory instanceof DigitalOceanExceptionFactory);
        ObjectReflector::setProperty(
            $digitaloceanExceptionFactory,
            DigitalOceanExceptionFactory::class,
            'digitalOceanClient',
            $digitalOceanClient
        );

        try {
            $callable();
            self::fail($dropletApiException::class . ' not thrown');
        } catch (Exception $exception) {
            self::assertSame($expectedExceptionClass, $exception::class);
            self::assertSame($action, $exception->getAction());
            self::assertEquals($dropletApiException, $exception->getRemoteException());
        }
    }
}
