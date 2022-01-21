<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\MachineProvider;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\Exception;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\MachineProvider\ProviderMachineNotFoundException;
use App\Model\DigitalOcean\RemoteMachine;
use App\Model\MachineActionInterface;
use App\Model\ProviderInterface;
use App\Services\Entity\Store\MachineProviderStore;
use App\Services\MachineManager;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\ApiLimitExceededException as VendorApiLimitExceededExceptionAlias;
use DigitalOceanV2\Exception\RuntimeException;
use DigitalOceanV2\Exception\ValidationFailedException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class MachineManagerTest extends AbstractBaseFunctionalTest
{
    private const MACHINE_ID = 'machine id';

    private MachineManager $machineManager;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $machineManager = self::$container->get(MachineManager::class);
        \assert($machineManager instanceof MachineManager);
        $this->machineManager = $machineManager;

        $mockHandler = self::$container->get(MockHandler::class);
        if ($mockHandler instanceof MockHandler) {
            $this->mockHandler = $mockHandler;
        }
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

        $expectedDropletEntity = new DropletEntity($dropletData);
        $this->mockHandler->append(HttpResponseFactory::fromDropletEntity($expectedDropletEntity));

        $remoteMachine = $this->machineManager->create($this->createMachineProvider());

        self::assertEquals(new RemoteMachine($expectedDropletEntity), $remoteMachine);
    }

    /**
     * @dataProvider remoteRequestThrowsExceptionDataProvider
     *
     * @param class-string $expectedExceptionClass
     */
    public function testCreateThrowsException(
        ResponseInterface $apiResponse,
        string $expectedExceptionClass,
        \Exception $expectedRemoveException
    ): void {
        $this->doActionThrowsExceptionTest(
            function () {
                $this->machineManager->create($this->createMachineProvider());
            },
            MachineActionInterface::ACTION_CREATE,
            $apiResponse,
            $expectedExceptionClass,
            $expectedRemoveException
        );
    }

    public function testCreateThrowsDropletLimitException(): void
    {
        $this->mockHandler->append(
            new Response(
                422,
                [
                    'content-type' => 'application/json',
                ],
                (string) json_encode([
                    'id' => 'unprocessable_entity',
                    'message' => 'creating this/these droplet(s) will exceed your droplet limit',
                ])
            )
        );

        try {
            $this->machineManager->create($this->createMachineProvider());
            self::fail(ExceptionInterface::class . ' not thrown');
        } catch (ExceptionInterface $exception) {
            self::assertEquals(
                new ValidationFailedException('creating this/these droplet(s) will exceed your droplet limit', 422),
                $exception->getRemoteException()
            );
        }
    }

    public function testGetSuccess(): void
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

        $expectedDropletEntity = new DropletEntity($dropletData);
        $this->mockHandler->append(HttpResponseFactory::fromDropletEntityCollection([$expectedDropletEntity]));

        $remoteMachine = $this->machineManager->get($this->createMachineProvider());

        self::assertEquals(new RemoteMachine($expectedDropletEntity), $remoteMachine);
    }

    public function testGetThrowsMachineNotFoundException(): void
    {
        $this->mockHandler->append(HttpResponseFactory::fromDropletEntityCollection([]));

        $machineProvider = $this->createMachineProvider();

        self::expectExceptionObject(new ProviderMachineNotFoundException(
            $machineProvider->getId(),
            $machineProvider->getName()
        ));

        $this->machineManager->get($machineProvider);
    }

    /**
     * @dataProvider remoteRequestThrowsExceptionDataProvider
     *
     * @param class-string $expectedExceptionClass
     */
    public function testGetThrowsException(
        ResponseInterface $apiResponse,
        string $expectedExceptionClass,
        \Exception $expectedRemoveException
    ): void {
        $this->doActionThrowsExceptionTest(
            function () {
                $this->machineManager->get($this->createMachineProvider());
            },
            MachineActionInterface::ACTION_GET,
            $apiResponse,
            $expectedExceptionClass,
            $expectedRemoveException
        );
    }

    public function testDeleteSuccess(): void
    {
        $this->mockHandler->append(new Response(204));

        $this->machineManager->delete($this->createMachineProvider());
        self::expectNotToPerformAssertions();
    }

    /**
     * @dataProvider remoteRequestThrowsExceptionDataProvider
     *
     * @param class-string $expectedExceptionClass
     */
    public function testDeleteThrowsException(
        ResponseInterface $apiResponse,
        string $expectedExceptionClass,
        \Exception $expectedRemoveException
    ): void {
        $this->doActionThrowsExceptionTest(
            function () {
                $this->machineManager->delete($this->createMachineProvider());
            },
            MachineActionInterface::ACTION_DELETE,
            $apiResponse,
            $expectedExceptionClass,
            $expectedRemoveException
        );
    }

    /**
     * @return array<mixed>
     */
    public function remoteRequestThrowsExceptionDataProvider(): array
    {
        return [
            VendorApiLimitExceededExceptionAlias::class => [
                'apiResponse' => new Response(
                    429,
                    [
                        'RateLimit-Reset' => 123,
                    ]
                ),
                'expectedExceptionClass' => ApiLimitExceededException::class,
                'expectedRemoteException' => new VendorApiLimitExceededExceptionAlias('Too Many Requests', 429),
            ],
            RuntimeException::class . ' HTTP 503' => [
                'apiResponse' => new Response(503),
                'expectedExceptionClass' => HttpException::class,
                'expectedRemoteException' => new RuntimeException('Service Unavailable', 503),
            ],
            ValidationFailedException::class => [
                'apiResponse' => new Response(400),
                'expectedExceptionClass' => Exception::class,
                'expectedRemoteException' => new ValidationFailedException('Bad Request', 400),
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
        ResponseInterface $apiResponse,
        string $expectedExceptionClass,
        \Throwable $expectedRemoteException
    ): void {
        $this->mockHandler->append($apiResponse);

        try {
            $callable();
            self::fail(ExceptionInterface::class . ' not thrown');
        } catch (Exception $exception) {
            self::assertSame($expectedExceptionClass, $exception::class);
            self::assertSame($action, $exception->getAction());
            self::assertEquals($expectedRemoteException, $exception->getRemoteException());
        }
    }

    private function createMachineProvider(): MachineProvider
    {
        $machineProviderStore = self::$container->get(MachineProviderStore::class);
        \assert($machineProviderStore instanceof MachineProviderStore);
        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        $machineProviderStore->store($machineProvider);

        return $machineProvider;
    }
}
