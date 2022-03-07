<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Machine;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\Exception;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Model\DigitalOcean\RemoteMachine;
use App\Model\MachineActionInterface;
use App\Services\DigitalOceanMachineManager;
use App\Services\Entity\Store\MachineStore;
use App\Services\ExceptionFactory\MachineProvider\DigitalOceanExceptionFactory;
use App\Services\MachineNameFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Proxy\DigitalOceanV2\Api\DropletApiProxy;
use DigitalOceanV2\Client as DigitaloceanClient;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\ApiLimitExceededException as VendorApiLimitExceededException;
use DigitalOceanV2\Exception\RuntimeException;
use DigitalOceanV2\Exception\ValidationFailedException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use SmartAssert\DigitalOceanDropletConfiguration\Configuration;
use SmartAssert\DigitalOceanDropletConfiguration\Factory;
use webignition\ObjectReflector\ObjectReflector;

class DigitalOceanMachineManagerTest extends AbstractBaseFunctionalTest
{
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

        $machineStore = self::getContainer()->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $this->machine = new Machine(self::MACHINE_ID);
        $machineStore->store($this->machine);

        $machineNameFactory = self::getContainer()->get(MachineNameFactory::class);
        \assert($machineNameFactory instanceof MachineNameFactory);
        $this->machineName = $machineNameFactory->create(self::MACHINE_ID);

        $dropletApiProxy = self::getContainer()->get(DropletApiProxy::class);
        \assert($dropletApiProxy instanceof DropletApiProxy);
        $this->dropletApiProxy = $dropletApiProxy;
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
        $this->setDropletApiProxyCreateCallExpectation($droplet);

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
                $this->setDropletApiProxyCreateCallExpectation($dropletApiException);
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
        $createOutcome = new ValidationFailedException(
            'creating this/these droplet(s) will exceed your droplet limit',
            422
        );

        $this->setDropletApiProxyCreateCallExpectation($createOutcome);

        try {
            $this->machineManager->create(self::MACHINE_ID, $this->machineName);
            self::fail(ExceptionInterface::class . ' not thrown');
        } catch (ExceptionInterface $exception) {
            self::assertSame($createOutcome, $exception->getRemoteException());
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
     * @return array<mixed>
     */
    public function remoteRequestThrowsExceptionDataProvider(): array
    {
        return [
            VendorApiLimitExceededException::class => [
                'dropletApiException' => new VendorApiLimitExceededException('Too Many Requests', 429),
                'apiResponse' => new Response(
                    429,
                    [
                        'RateLimit-Reset' => '123',
                    ]
                ),
                'expectedExceptionClass' => ApiLimitExceededException::class,
            ],
            RuntimeException::class . ' HTTP 503' => [
                'dropletApiException' => new RuntimeException('Service Unavailable', 503),
                'apiResponse' => new Response(503),
                'expectedExceptionClass' => HttpException::class,
            ],
            ValidationFailedException::class => [
                'dropletApiException' => new ValidationFailedException('Bad Request', 400),
                'apiResponse' => new Response(400),
                'expectedExceptionClass' => Exception::class,
            ],
        ];
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

    private function setDropletApiProxyCreateCallExpectation(DropletEntity | \Exception $outcome): void
    {
        $dropletConfiguration = $this->createDropletConfiguration($this->machineName);

        $this->dropletApiProxy->withCreateCall(
            $this->machineName,
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
        $factory = self::getContainer()->get(Factory::class);
        if (false === $factory instanceof Factory) {
            throw new \RuntimeException(Factory::class . ' service not found');
        }

        $configuration = $factory->create();
        $configuration = $configuration->withNames([$name]);

        return $configuration->addTags([$name]);
    }
}
