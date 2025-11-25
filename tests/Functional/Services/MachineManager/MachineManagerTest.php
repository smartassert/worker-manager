<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\MachineManager;

use App\Entity\Machine;
use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Enum\MachineState;
use App\Exception\MachineActionFailedException;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\Exception;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\MachineProvider\ProviderMachineNotFoundException;
use App\Exception\Stack;
use App\Model\DigitalOcean\RemoteMachine;
use App\Services\MachineManager\DigitalOcean\Entity\Droplet;
use App\Services\MachineManager\DigitalOcean\Entity\Network;
use App\Services\MachineManager\DigitalOcean\Entity\NetworkCollection;
use App\Services\MachineManager\DigitalOcean\Exception\ErrorException;
use App\Services\MachineManager\MachineManager;
use App\Tests\AbstractBaseFunctionalTestCase;
use App\Tests\Services\EntityRemover;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;

class MachineManagerTest extends AbstractBaseFunctionalTestCase
{
    private const MACHINE_ID = 'machine id';

    private MachineManager $machineManager;

    protected function setUp(): void
    {
        parent::setUp();

        $machineManager = self::getContainer()->get(MachineManager::class);
        \assert($machineManager instanceof MachineManager);
        $this->machineManager = $machineManager;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
        }
    }

    public function testCreateSuccess(): void
    {
        $ipAddresses = ['10.0.0.1', '127.0.0.1'];

        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $dropletId = rand(1, PHP_INT_MAX);
        $dropletStatus = RemoteMachine::STATE_NEW;
        $mockHandler->append(new Response(
            200,
            [
                'Content-Type' => 'application/json',
            ],
            (string) json_encode([
                'droplet' => [
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
            ]),
        ));

        $expectedDropletEntity = new Droplet(
            $dropletId,
            RemoteMachine::STATE_NEW,
            new NetworkCollection([
                new Network($ipAddresses[0], true, 4),
                new Network($ipAddresses[1], true, 4),
            ])
        );

        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::CREATE_RECEIVED);
        $remoteMachine = $this->machineManager->create($machine);

        self::assertEquals(new RemoteMachine($expectedDropletEntity), $remoteMachine);
    }

    /**
     * @param class-string $expectedExceptionClass
     */
    #[DataProvider('getDeleteThrowsExceptionDataProvider')]
    public function testCreateThrowsException(ResponseInterface $httpResponse, string $expectedExceptionClass): void
    {
        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $mockHandler->append($httpResponse);
        $mockHandler->append($httpResponse);

        try {
            $machine = new Machine(self::MACHINE_ID);
            $machine->setState(MachineState::CREATE_RECEIVED);

            $this->machineManager->create($machine);

            self::fail(MachineActionFailedException::class . ' not thrown');
        } catch (MachineActionFailedException $exception) {
            $innerException = $exception->getExceptionStack()->first();
            self::assertInstanceOf(ExceptionInterface::class, $innerException);
            self::assertSame($expectedExceptionClass, $innerException::class);
            self::assertSame(MachineAction::CREATE, $innerException->getAction());
        }
    }

    public function testCreateThrowsDropletLimitException(): void
    {
        $rateLimitReset = (\time() + 1000);

        $httpResponse = new Response(
            422,
            [
                'Content-Type' => 'application/json',
                'RateLimit-limit' => '5000',
                'RateLimit-Remaining' => '0',
                'RateLimit-Reset' => (string) $rateLimitReset,
                'Retry-After' => '1000',
            ],
            (string) json_encode([
                'id' => 'droplet_limit_exceeded',
                'message' => 'creating this/these droplet(s) will exceed your droplet limit',
            ])
        );

        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $mockHandler->append($httpResponse);
        $mockHandler->append($httpResponse);

        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::CREATE_RECEIVED);

        try {
            $this->machineManager->create($machine);
            self::fail(ExceptionInterface::class . ' not thrown');
        } catch (MachineActionFailedException $exception) {
            $innerException = $exception->getExceptionStack()->first();
            self::assertInstanceOf(ExceptionInterface::class, $innerException);
        }
    }

    public function testGetSuccess(): void
    {
        $ipAddresses = ['10.0.0.1', '127.0.0.1'];

        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $dropletId = rand(1, PHP_INT_MAX);
        $dropletStatus = RemoteMachine::STATE_NEW;
        $mockHandler->append(new Response(
            200,
            [
                'Content-Type' => 'application/json',
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

        $expectedDropletEntity = new Droplet(
            $dropletId,
            RemoteMachine::STATE_NEW,
            new NetworkCollection([
                new Network($ipAddresses[0], true, 4),
                new Network($ipAddresses[1], true, 4),
            ])
        );

        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::CREATE_RECEIVED);
        $machine->setProvider(MachineProvider::DIGITALOCEAN);

        $remoteMachine = $this->machineManager->get($machine);

        self::assertEquals(new RemoteMachine($expectedDropletEntity), $remoteMachine);
    }

    public function testGetThrowsMachineNotFoundException(): void
    {
        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $mockHandler->append(new Response(
            200,
            [
                'Content-Type' => 'application/json',
            ],
            (string) json_encode([
                'droplets' => [],
            ]),
        ));

        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::CREATE_RECEIVED);
        $machine->setProvider(MachineProvider::DIGITALOCEAN);

        self::expectExceptionObject(new ProviderMachineNotFoundException(
            $machine->getId(),
            MachineProvider::DIGITALOCEAN->value
        ));

        $this->machineManager->get($machine);
    }

    /**
     * @param class-string $expectedExceptionClass
     */
    #[DataProvider('getDeleteThrowsExceptionDataProvider')]
    public function testGetThrowsException(ResponseInterface $httpResponse, string $expectedExceptionClass): void
    {
        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::CREATE_RECEIVED);
        $machine->setProvider(MachineProvider::DIGITALOCEAN);

        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $mockHandler->append($httpResponse);
        $mockHandler->append($httpResponse);

        $exception = null;

        try {
            $this->machineManager->get($machine);
        } catch (Exception $exception) {
        }

        self::assertNotNull($exception);
        self::assertSame($expectedExceptionClass, $exception::class);
        self::assertSame(MachineAction::GET, $exception->getAction());
    }

    #[DataProvider('removeSuccessDataProvider')]
    public function testRemoveSuccess(ResponseInterface $httpResponse): void
    {
        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $mockHandler->append($httpResponse);
        $mockHandler->append($httpResponse);

        $this->expectNotToPerformAssertions();

        $this->machineManager->remove(self::MACHINE_ID);
    }

    /**
     * @return array<mixed>
     */
    public static function removeSuccessDataProvider(): array
    {
        return [
            'removed' => [
                'httpResponse' => new Response(204),
            ],
            'not found' => [
                'httpResponse' => new Response(404),
            ],
        ];
    }

    /**
     * @param class-string $expectedExceptionClass
     */
    #[DataProvider('getDeleteThrowsExceptionDataProvider')]
    public function testRemoveThrowsException(ResponseInterface $httpResponse, string $expectedExceptionClass): void
    {
        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $mockHandler->append($httpResponse);
        $mockHandler->append($httpResponse);

        $exception = null;

        try {
            $this->machineManager->remove(self::MACHINE_ID);
        } catch (MachineActionFailedException $exception) {
        }

        self::assertInstanceOf(MachineActionFailedException::class, $exception);

        $innerException = $exception->getExceptionStack()->first();

        self::assertInstanceOf(ExceptionInterface::class, $innerException);
        self::assertSame($expectedExceptionClass, $innerException::class);
        self::assertSame(MachineAction::DELETE, $innerException->getAction());
    }

    /**
     * @return array<mixed>
     */
    public static function getDeleteThrowsExceptionDataProvider(): array
    {
        $rateLimitReset = (\time() + 1000);

        return [
            'unauthorized' => [
                'httpResponse' => new Response(401),
                'expectedExceptionClass' => AuthenticationException::class,
            ],
            'api limit exceeded' => [
                'httpResponse' => new Response(
                    429,
                    [
                        'Content-Type' => 'application/json',
                        'RateLimit-limit' => '5000',
                        'RateLimit-Remaining' => '0',
                        'RateLimit-Reset' => (string) $rateLimitReset,
                        'Retry-After' => '1000',
                    ],
                    (string) json_encode([
                        'id' => 'too_many_requests',
                        'message' => 'API Rate limit exceeded',
                    ])
                ),
                'expectedExceptionClass' => ApiLimitExceededException::class,
            ],
        ];
    }

    public function testRemoveThrowsMachineNotRemovableException(): void
    {
        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $httpResponse = new Response(
            503,
            [
                'Content-Type' => 'application/json',
            ],
            (string) json_encode([
                'id' => 'service_unavailable',
                'message' => 'Service unavailable',
            ])
        );

        $mockHandler->append($httpResponse);
        $mockHandler->append($httpResponse);

        $expectedExceptionStack = new Stack([
            new HttpException(
                self::MACHINE_ID,
                MachineAction::DELETE,
                new ErrorException('service_unavailable', 'Service unavailable', 503)
            ),
        ]);

        try {
            $this->machineManager->remove(self::MACHINE_ID);
            self::fail(MachineActionFailedException::class . ' not thrown');
        } catch (MachineActionFailedException $machineNotFoundException) {
            self::assertEquals($expectedExceptionStack, $machineNotFoundException->getExceptionStack());
        }
    }

    public function testFindSuccess(): void
    {
        $dropletId = rand(1, PHP_INT_MAX);
        $dropletStatus = RemoteMachine::STATE_NEW;

        $expectedDropletEntity = new Droplet($dropletId, RemoteMachine::STATE_NEW, new NetworkCollection([]));

        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $mockHandler->append(new Response(
            200,
            [
                'Content-Type' => 'application/json',
            ],
            (string) json_encode([
                'droplets' => [
                    [
                        'id' => $dropletId,
                        'status' => $dropletStatus,
                    ],
                ],
            ]),
        ));

        $remoteMachine = $this->machineManager->find(self::MACHINE_ID);

        self::assertEquals(new RemoteMachine($expectedDropletEntity), $remoteMachine);
    }

    public function testFindMachineNotFindable(): void
    {
        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $httpResponse = new Response(
            503,
            [
                'Content-Type' => 'application/json',
            ],
            (string) json_encode([
                'id' => 'service_unavailable',
                'message' => 'Service unavailable',
            ])
        );

        $mockHandler->append($httpResponse);
        $mockHandler->append($httpResponse);

        $expectedExceptionStack = new Stack([
            new HttpException(
                self::MACHINE_ID,
                MachineAction::FIND,
                new ErrorException('service_unavailable', 'Service unavailable', 503)
            ),
        ]);

        try {
            $this->machineManager->find(self::MACHINE_ID);
            self::fail(MachineActionFailedException::class . ' not thrown');
        } catch (MachineActionFailedException $machineNotFoundException) {
            self::assertEquals($expectedExceptionStack, $machineNotFoundException->getExceptionStack());
        }
    }

    public function testFindMachineDoesNotExist(): void
    {
        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $mockHandler->append(new Response(
            200,
            [
                'Content-Type' => 'application/json',
            ],
            (string) json_encode([
                'droplets' => [],
            ]),
        ));

        self::assertNull($this->machineManager->find(self::MACHINE_ID));
    }
}
