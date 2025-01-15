<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Enum\MachineState;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\InvalidEntityResponseException;
use App\Exception\Stack;
use App\Exception\UnsupportedProviderException;
use App\Message\GetMachine;
use App\MessageHandler\GetMachineHandler;
use App\Model\DigitalOcean\RemoteMachine;
use App\Repository\MachineRepository;
use App\Services\MachineManager\DigitalOcean\Exception\ApiLimitExceededException as DOApiLimitExceededException;
use App\Services\MachineManager\DigitalOcean\Exception\AuthenticationException as DigitalOceanAuthenticationException;
use App\Services\MachineManager\DigitalOcean\Exception\ErrorException;
use App\Services\MachineManager\DigitalOcean\Exception\InvalidEntityDataException;
use App\Services\MachineManager\MachineManager;
use App\Services\MachineRequestDispatcher;
use App\Services\MachineUpdater;
use App\Tests\AbstractBaseFunctionalTestCase;
use App\Tests\Services\EntityRemover;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class GetMachineHandlerTest extends AbstractBaseFunctionalTestCase
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'machine id';
    private const REMOTE_ID = 123;

    private MachineRepository $machineRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $this->machineRepository = $machineRepository;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
        }
    }

    public function testHandlerExistsInContainerAndIsAMessageHandler(): void
    {
        $handler = self::getContainer()->get(GetMachineHandler::class);
        self::assertInstanceOf(GetMachineHandler::class, $handler);
        self::assertCount(1, (new \ReflectionClass($handler::class))->getAttributes(AsMessageHandler::class));
    }

    /**
     * @param array<mixed> $responseData
     */
    #[DataProvider('invokeSuccessDataProvider')]
    public function testInvokeSuccess(array $responseData, Machine $machine, Machine $expectedMachine): void
    {
        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $mockHandler->append(new Response(
            200,
            [
                'Content-Type' => 'application/json'
            ],
            (string) json_encode($responseData),
        ));

        $this->machineRepository->add($machine);

        $message = new GetMachine('id0', $machine->getId());

        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher->shouldNotReceive('dispatch');
        $machineRequestDispatcher
            ->shouldReceive('dispatchCollection')
            ->with([])
            ->andReturn([])
        ;

        $handler = $this->createHandler($machineRequestDispatcher);
        ($handler)($message);

        self::assertEquals($expectedMachine, $machine);
    }

    /**
     * @return array<mixed>
     */
    public static function invokeSuccessDataProvider(): array
    {
        $ipAddresses = [
            '10.0.0.1',
            '127.0.0.1',
        ];

        return [
            'updated within initial remote id and initial remote state' => [
                'responseData' => [
                    'droplets' => [
                        [
                            'id' => self::REMOTE_ID,
                            'status' => RemoteMachine::STATE_NEW,
                        ],
                    ],
                ],
                'machine' => (function () {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::CREATE_RECEIVED);
                    $machine->setProvider(MachineProvider::DIGITALOCEAN);

                    return $machine;
                })(),
                'expectedMachine' => (function () {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::UP_STARTED);
                    $machine->setProvider(MachineProvider::DIGITALOCEAN);

                    return $machine;
                })(),
            ],
            'updated within initial ip addresses' => [
                'responseData' => [
                    'droplets' => [
                        [
                            'id' => self::REMOTE_ID,
                            'status' => RemoteMachine::STATE_NEW,
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
                ],
                'machine' => (function () {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::UP_STARTED);
                    $machine->setProvider(MachineProvider::DIGITALOCEAN);

                    return $machine;
                })(),
                'expectedMachine' => (function (array $ipAddresses) {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::UP_STARTED);
                    $machine->setIpAddresses($ipAddresses);
                    $machine->setProvider(MachineProvider::DIGITALOCEAN);

                    return $machine;
                })($ipAddresses),
            ],
            'updated within active remote state' => [
                'responseData' => [
                    'droplets' => [
                        [
                            'id' => self::REMOTE_ID,
                            'status' => RemoteMachine::STATE_ACTIVE,
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
                ],
                'machine' => (function (array $ipAddresses) {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::UP_STARTED);
                    $machine->setIpAddresses($ipAddresses);
                    $machine->setProvider(MachineProvider::DIGITALOCEAN);

                    return $machine;
                })($ipAddresses),
                'expectedMachine' => (function (array $ipAddresses) {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::UP_ACTIVE);
                    $machine->setIpAddresses($ipAddresses);
                    $machine->setProvider(MachineProvider::DIGITALOCEAN);

                    return $machine;
                })($ipAddresses),
            ],
        ];
    }

    public function testInvokeUnsupportedProvider(): void
    {
        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::FIND_RECEIVED);
        $this->machineRepository->add($machine);

        $message = new GetMachine('id0', $machine->getId());
        $machineState = $machine->getState();

        $unsupportedProviderException = new UnsupportedProviderException(null);
        $expectedException = new UnrecoverableMessageHandlingException(
            $unsupportedProviderException->getMessage(),
            $unsupportedProviderException->getCode(),
            $unsupportedProviderException
        );

        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $handler = $this->createHandler($machineRequestDispatcher);

        try {
            ($handler)($message);
            $this->fail($expectedException::class . ' not thrown');
        } catch (\Exception $exception) {
            self::assertEquals($expectedException, $exception);
        }

        self::assertSame($machineState, $machine->getState());
    }

    #[DataProvider('invokeThrowsExceptionDataProvider')]
    public function testInvokeThrowsException(ResponseInterface $httpResponse, \Exception $expectedException): void
    {
        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::FIND_RECEIVED);
        $machine->setProvider(MachineProvider::DIGITALOCEAN);
        $this->machineRepository->add($machine);

        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $mockHandler->append($httpResponse);
        $mockHandler->append($httpResponse);

        $message = new GetMachine('id0', $machine->getId());
        $machineState = $machine->getState();

        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $handler = $this->createHandler($machineRequestDispatcher);

        try {
            ($handler)($message);
            $this->fail($expectedException::class . ' not thrown');
        } catch (\Exception $exception) {
            self::assertEquals($expectedException, $exception);
        }

        self::assertSame($machineState, $machine->getState());
    }

    /**
     * @return array<mixed>
     */
    public static function invokeThrowsExceptionDataProvider(): array
    {
        $rateLimitReset = (\time() + 1000);

        $internalServerErrorId = md5((string) rand());
        $internalServerErrorMessage = md5((string) rand());

        $serviceUnavailableErrorId = md5((string) rand());
        $serviceUnavailableErrorMessage = md5((string) rand());

        return [
            'unauthorized' => [
                'httpResponse' => new Response(401),
                'expectedException' => new UnrecoverableMessageHandlingException(
                    'AuthenticationException Unable to perform action "get" for resource "machine id"',
                    0,
                    new AuthenticationException(
                        MachineProvider::DIGITALOCEAN,
                        self::MACHINE_ID,
                        MachineAction::GET,
                        new Stack([new DigitalOceanAuthenticationException()])
                    )
                ),
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
                'expectedException' => new UnrecoverableMessageHandlingException(
                    'ApiLimitExceededException Unable to perform action "get" for resource "machine id"',
                    0,
                    new ApiLimitExceededException(
                        $rateLimitReset,
                        self::MACHINE_ID,
                        MachineAction::GET,
                        new DOApiLimitExceededException(
                            'API Rate limit exceeded',
                            $rateLimitReset,
                            0,
                            5000
                        ),
                    )
                ),
            ],
            'internal server error' => [
                'httpResponse' => new Response(
                    500,
                    [
                        'Content-Type' => 'application/json',
                    ],
                    (string) json_encode([
                        'id' => $internalServerErrorId,
                        'message' => $internalServerErrorMessage,
                    ])
                ),
                'expectedException' => new HttpException(
                    self::MACHINE_ID,
                    MachineAction::GET,
                    new ErrorException(
                        $internalServerErrorId,
                        $internalServerErrorMessage,
                        500
                    )
                ),
            ],
            'service unavailable' => [
                'httpResponse' => new Response(
                    503,
                    [
                        'Content-Type' => 'application/json',
                    ],
                    (string) json_encode([
                        'id' => $serviceUnavailableErrorId,
                        'message' => $serviceUnavailableErrorMessage,
                    ])
                ),
                'expectedException' => new HttpException(
                    self::MACHINE_ID,
                    MachineAction::GET,
                    new ErrorException(
                        $serviceUnavailableErrorId,
                        $serviceUnavailableErrorMessage,
                        503
                    )
                ),
            ],
            'invalid droplet data (empty)' => [
                'httpResponse' => new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    (string) json_encode([])
                ),
                'expectedException' => new UnrecoverableMessageHandlingException(
                    'InvalidEntityResponseException Unable to perform action "get" for resource "machine id"',
                    0,
                    new InvalidEntityResponseException(
                        MachineProvider::DIGITALOCEAN,
                        [],
                        self::MACHINE_ID,
                        MachineAction::GET,
                        new InvalidEntityDataException('droplet_as_collection', []),
                    ),
                ),
            ],
            'invalid droplet data (lacking fields)' => [
                'httpResponse' => new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    (string) json_encode([
                        'droplets' => [
                            [
                                'id' => 123,
                            ],
                        ],
                    ])
                ),
                'expectedException' => new UnrecoverableMessageHandlingException(
                    'InvalidEntityResponseException Unable to perform action "get" for resource "machine id"',
                    0,
                    new InvalidEntityResponseException(
                        MachineProvider::DIGITALOCEAN,
                        ['id' => '123'],
                        self::MACHINE_ID,
                        MachineAction::GET,
                        new InvalidEntityDataException('droplet', ['id' => '123']),
                    ),
                ),
            ],
        ];
    }

    private function createHandler(MachineRequestDispatcher $machineRequestDispatcher): GetMachineHandler
    {
        $machineManager = self::getContainer()->get(MachineManager::class);
        \assert($machineManager instanceof MachineManager);

        $machineUpdater = self::getContainer()->get(MachineUpdater::class);
        \assert($machineUpdater instanceof MachineUpdater);

        return new GetMachineHandler(
            $machineManager,
            $machineRequestDispatcher,
            $machineUpdater,
            $this->machineRepository,
        );
    }
}
