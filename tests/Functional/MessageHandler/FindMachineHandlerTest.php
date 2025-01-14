<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Enum\MachineState;
use App\Exception\MachineActionFailedException;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException as LocalApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\Stack;
use App\Message\FindMachine;
use App\Message\MachineRequestInterface;
use App\MessageHandler\FindMachineHandler;
use App\Model\DigitalOcean\RemoteMachine;
use App\Repository\MachineRepository;
use App\Services\MachineManager\DigitalOcean\Exception\AuthenticationException as DigitalOceanAuthenticationException;
use App\Services\MachineManager\DigitalOcean\Exception\ErrorException;
use App\Services\MachineManager\MachineManager;
use App\Services\MachineRequestDispatcher;
use App\Services\MachineUpdater;
use App\Tests\AbstractBaseFunctionalTestCase;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\TestMachineRequestFactory;
use DigitalOceanV2\Entity\RateLimit;
use DigitalOceanV2\Exception\ApiLimitExceededException as VendorApiLimitExceededException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class FindMachineHandlerTest extends AbstractBaseFunctionalTestCase
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

    private MachineRepository $machineRepository;
    private TestMachineRequestFactory $machineRequestFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $this->machineRepository = $machineRepository;

        $machineRequestFactory = self::getContainer()->get(TestMachineRequestFactory::class);
        \assert($machineRequestFactory instanceof TestMachineRequestFactory);
        $this->machineRequestFactory = $machineRequestFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
        }
    }

    public function testHandlerExistsInContainerAndIsAMessageHandler(): void
    {
        $handler = self::getContainer()->get(FindMachineHandler::class);
        self::assertInstanceOf(FindMachineHandler::class, $handler);
        self::assertCount(1, (new \ReflectionClass($handler::class))->getAttributes(AsMessageHandler::class));
    }

    /**
     * @param array<mixed>                                              $responseData
     * @param callable(FindMachine $message): MachineRequestInterface[] $expectedMachineRequestCollectionCreator
     * @param callable(TestMachineRequestFactory $factory): FindMachine $messageCreator
     */
    #[DataProvider('invokeSuccessDataProvider')]
    public function testInvokeSuccess(
        Machine $machine,
        array $responseData,
        Machine $expectedMachine,
        callable $messageCreator,
        callable $expectedMachineRequestCollectionCreator,
    ): void {
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

        $message = $messageCreator($this->machineRequestFactory);
        $expectedMachineRequestCollection = $expectedMachineRequestCollectionCreator($message);

        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher
            ->shouldReceive('dispatchCollection')
            ->withArgs(function (array $machineRequestCollection) use ($expectedMachineRequestCollection) {
                self::assertEquals($expectedMachineRequestCollection, $machineRequestCollection);

                return true;
            })
        ;

        $handler = $this->createHandler($machineRequestDispatcher);
        ($handler)($message);

        self::assertEquals($expectedMachine, $this->machineRepository->find(self::MACHINE_ID));
    }

    /**
     * @return array<mixed>
     */
    public static function invokeSuccessDataProvider(): array
    {
        $machineFoundResponseData = [
            'droplets' => [
                [
                    'id' => rand(),
                    'status' => RemoteMachine::STATE_NEW,
                    'networks' => [
                        'v4' => [
                            [
                                'ip_address' => '10.0.0.1',
                                'type' => 'public',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return [
            'remote machine found and updated, no existing provider' => [
                'machine' => (function () {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::FIND_RECEIVED);

                    return $machine;
                })(),
                'responseData' => $machineFoundResponseData,
                'expectedMachine' => (function () {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::UP_STARTED);
                    $machine->setIpAddresses(['10.0.0.1']);
                    $machine->setProvider(MachineProvider::DIGITALOCEAN);

                    return $machine;
                })(),
                'messageCreator' => function (TestMachineRequestFactory $factory) {
                    return $factory->createFind(
                        self::MACHINE_ID,
                        [$factory->createCheckIsActive(self::MACHINE_ID)],
                    );
                },
                'expectedMachineRequestCollectionCreator' => function (FindMachine $message): array {
                    return $message->getOnSuccessCollection();
                },
            ],
            'remote machine found and updated, has existing provider' => [
                'machine' => (function () {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::FIND_RECEIVED);
                    $machine->setProvider(MachineProvider::DIGITALOCEAN);

                    return $machine;
                })(),
                'responseData' => $machineFoundResponseData,
                'expectedMachine' => (function () {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::UP_STARTED);
                    $machine->setIpAddresses(['10.0.0.1']);
                    $machine->setProvider(MachineProvider::DIGITALOCEAN);

                    return $machine;
                })(),
                'messageCreator' => function (TestMachineRequestFactory $factory) {
                    return $factory->createFind(
                        self::MACHINE_ID,
                        [$factory->createCheckIsActive(self::MACHINE_ID)],
                    );
                },
                'expectedMachineRequestCollectionCreator' => function (FindMachine $message): array {
                    return $message->getOnSuccessCollection();
                },
            ],
            'remote machine not found, create requested' => [
                'machine' => (function () {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::FIND_RECEIVED);
                    $machine->setProvider(MachineProvider::DIGITALOCEAN);

                    return $machine;
                })(),
                'responseData' => [
                    'droplets' => [],
                ],
                'expectedMachine' => (function () {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::FIND_NOT_FOUND);
                    $machine->setProvider(MachineProvider::DIGITALOCEAN);

                    return $machine;
                })(),
                'messageCreator' => function (TestMachineRequestFactory $factory) {
                    return $factory->createFind(
                        self::MACHINE_ID,
                        [$factory->createCheckIsActive(self::MACHINE_ID)],
                    );
                },
                'expectedMachineRequestCollectionCreator' => function (FindMachine $message): array {
                    return $message->getOnFailureCollection();
                },
            ],
            'remote machine found, re-dispatch self' => [
                'machine' => (function () {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::FIND_RECEIVED);
                    $machine->setProvider(MachineProvider::DIGITALOCEAN);

                    return $machine;
                })(),
                'responseData' => $machineFoundResponseData,
                'expectedMachine' => (function () {
                    $machine = new Machine(self::MACHINE_ID);
                    $machine->setState(MachineState::UP_STARTED);
                    $machine->setIpAddresses(['10.0.0.1']);
                    $machine->setProvider(MachineProvider::DIGITALOCEAN);

                    return $machine;
                })(),
                'messageCreator' => function (TestMachineRequestFactory $factory) {
                    $message = $factory->createFind(self::MACHINE_ID);

                    return $message->withReDispatchOnSuccess(true);
                },
                'expectedMachineRequestCollectionCreator' => function (FindMachine $message): array {
                    return [$message];
                },
            ],
        ];
    }

    public function testInvokeMachineEntityMissing(): void
    {
        $machineId = 'invalid machine id';

        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher->shouldNotReceive('dispatch');
        $machineRequestDispatcher->shouldNotReceive('dispatchCollection');

        $handler = $this->createHandler($machineRequestDispatcher);

        ($handler)(new FindMachine('id0', $machineId));

        self::assertNull($this->machineRepository->find($machineId));
    }

    #[DataProvider('invokeThrowsExceptionDataProvider')]
    public function testInvokeThrowsException(ResponseInterface $httpResponse, \Exception $expectedException): void
    {
        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::FIND_RECEIVED);
        $this->machineRepository->add($machine);

        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $mockHandler->append($httpResponse);
        $mockHandler->append($httpResponse);

        $message = new FindMachine('id0', $machine->getId());

        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher->shouldNotReceive('dispatch');
        $machineRequestDispatcher->shouldNotReceive('dispatchCollection');

        $handler = $this->createHandler($machineRequestDispatcher);

        try {
            ($handler)($message);
            $this->fail($expectedException::class . ' not thrown');
        } catch (\Exception $exception) {
            self::assertEquals($expectedException, $exception);
        }

        self::assertSame(MachineState::FIND_FINDING, $machine->getState());
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
                    'Action "find" on machine "id" failed',
                    0,
                    new MachineActionFailedException(
                        self::MACHINE_ID,
                        MachineAction::FIND,
                        new Stack([
                            new AuthenticationException(
                                MachineProvider::DIGITALOCEAN,
                                self::MACHINE_ID,
                                MachineAction::FIND,
                                new Stack([new DigitalOceanAuthenticationException()])
                            ),
                        ])
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
                    'Action "find" on machine "id" failed',
                    0,
                    new MachineActionFailedException(
                        self::MACHINE_ID,
                        MachineAction::FIND,
                        new Stack([
                            new LocalApiLimitExceededException(
                                $rateLimitReset,
                                self::MACHINE_ID,
                                MachineAction::FIND,
                                new VendorApiLimitExceededException(
                                    'API Rate limit exceeded',
                                    429,
                                    new RateLimit([
                                        'reset' => $rateLimitReset,
                                        'remaining' => 0,
                                        'limit' => 5000,
                                    ])
                                ),
                            )
                        ])
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
                'expectedException' => new UnrecoverableMessageHandlingException(
                    'Action "find" on machine "id" failed',
                    0,
                    new MachineActionFailedException(
                        self::MACHINE_ID,
                        MachineAction::FIND,
                        new Stack([
                            new HttpException(
                                self::MACHINE_ID,
                                MachineAction::FIND,
                                new ErrorException(
                                    $internalServerErrorId,
                                    $internalServerErrorMessage,
                                    500
                                )
                            ),
                        ])
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
                'expectedException' => new UnrecoverableMessageHandlingException(
                    'Action "find" on machine "id" failed',
                    0,
                    new MachineActionFailedException(
                        self::MACHINE_ID,
                        MachineAction::FIND,
                        new Stack([
                            new HttpException(
                                self::MACHINE_ID,
                                MachineAction::FIND,
                                new ErrorException(
                                    $serviceUnavailableErrorId,
                                    $serviceUnavailableErrorMessage,
                                    503
                                )
                            ),
                        ])
                    )
                ),
            ],
        ];
    }

    private function createHandler(MachineRequestDispatcher $machineRequestDispatcher): FindMachineHandler
    {
        $machineManager = self::getContainer()->get(MachineManager::class);
        \assert($machineManager instanceof MachineManager);

        $machineUpdater = self::getContainer()->get(MachineUpdater::class);
        \assert($machineUpdater instanceof MachineUpdater);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        return new FindMachineHandler(
            $machineManager,
            $machineUpdater,
            $machineRequestDispatcher,
            $this->machineRepository,
        );
    }
}
