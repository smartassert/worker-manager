<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Enum\MachineState;
use App\Exception\MachineActionFailedException;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\HttpClientException;
use App\Exception\MachineProvider\InvalidEntityResponseException;
use App\Exception\Stack;
use App\Message\CreateMachine;
use App\MessageHandler\CreateMachineHandler;
use App\Model\DigitalOcean\RemoteMachine;
use App\Repository\MachineRepository;
use App\Services\MachineManager\DigitalOcean\Exception\ApiLimitExceededException as DOApiLimitExceededException;
use App\Services\MachineManager\DigitalOcean\Exception\AuthenticationException as DOAuthenticationException;
use App\Services\MachineManager\DigitalOcean\Exception\ErrorException;
use App\Services\MachineManager\DigitalOcean\Exception\InvalidEntityDataException;
use App\Services\MachineManager\MachineManager;
use App\Services\MachineRequestDispatcher;
use App\Services\MachineUpdater;
use App\Tests\AbstractBaseFunctionalTestCase;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\TestMachineRequestFactory;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class CreateMachineHandlerTest extends AbstractBaseFunctionalTestCase
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'machine id';

    private CreateMachineHandler $handler;
    private Machine $machine;
    private TestMachineRequestFactory $machineRequestFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(CreateMachineHandler::class);
        \assert($handler instanceof CreateMachineHandler);
        $this->handler = $handler;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
        }

        $this->machine = new Machine(self::MACHINE_ID);
        $this->machine->setState(MachineState::CREATE_RECEIVED);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $machineRepository->add($this->machine);

        $machineRequestFactory = self::getContainer()->get(TestMachineRequestFactory::class);
        \assert($machineRequestFactory instanceof TestMachineRequestFactory);
        $this->machineRequestFactory = $machineRequestFactory;
    }

    public function testHandlerExistsInContainerAndIsAMessageHandler(): void
    {
        $handler = self::getContainer()->get(CreateMachineHandler::class);
        self::assertInstanceOf(CreateMachineHandler::class, $handler);
        self::assertCount(1, (new \ReflectionClass($handler::class))->getAttributes(AsMessageHandler::class));
    }

    public function testInvokeSuccess(): void
    {
        self::assertSame([], $this->machine->getIpAddresses());

        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $ipAddresses = ['10.0.0.1', '127.0.0.1'];

        $mockHandler->append(new Response(
            200,
            [
                'Content-Type' => 'application/json'
            ],
            (string) json_encode([
                'droplet' => [
                    'id' => rand(),
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
            ]),
        ));

        $message = $this->machineRequestFactory->createCreate($this->machine->getId());
        $expectedMachineRequestCollection = $message->getOnSuccessCollection();

        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher
            ->shouldReceive('dispatchCollection')
            ->withArgs(function (array $machineRequestCollection) use ($expectedMachineRequestCollection) {
                self::assertEquals($expectedMachineRequestCollection, $machineRequestCollection);

                return true;
            })
        ;

        self::assertNull($this->machine->getProvider());

        $handler = $this->createHandler($machineRequestDispatcher);
        ($handler)($message);

        self::assertSame(MachineState::UP_STARTED, $this->machine->getState());
        self::assertSame($ipAddresses, $this->machine->getIpAddresses());
        self::assertSame(MachineProvider::DIGITALOCEAN, $this->machine->getProvider());
    }

    #[DataProvider('invokeThrowsExceptionDataProvider')]
    public function testInvokeThrowsException(
        ResponseInterface|\Throwable $httpResponse,
        \Exception $expectedException
    ): void {
        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $mockHandler->append($httpResponse);
        $mockHandler->append($httpResponse);

        $message = new CreateMachine('id0', $this->machine->getId());
        $exception = null;

        try {
            ($this->handler)($message);
            $this->fail($expectedException::class . ' not thrown');
        } catch (\Exception $exception) {
        }

        self::assertEquals($expectedException, $exception);
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
                    'Action "create" on machine "' . self::MACHINE_ID . '" failed',
                    0,
                    new MachineActionFailedException(
                        self::MACHINE_ID,
                        MachineAction::CREATE,
                        new Stack([
                            new AuthenticationException(
                                MachineProvider::DIGITALOCEAN,
                                self::MACHINE_ID,
                                MachineAction::CREATE,
                                new Stack([new DOAuthenticationException()])
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
                    'Action "create" on machine "' . self::MACHINE_ID . '" failed',
                    0,
                    new MachineActionFailedException(
                        self::MACHINE_ID,
                        MachineAction::CREATE,
                        new Stack([
                            new ApiLimitExceededException(
                                $rateLimitReset,
                                self::MACHINE_ID,
                                MachineAction::CREATE,
                                new DOApiLimitExceededException(
                                    'API Rate limit exceeded',
                                    $rateLimitReset,
                                    0,
                                    5000
                                ),
                            ),
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
                    'Action "create" on machine "' . self::MACHINE_ID . '" failed',
                    0,
                    new MachineActionFailedException(
                        self::MACHINE_ID,
                        MachineAction::CREATE,
                        new Stack([
                            new HttpException(
                                self::MACHINE_ID,
                                MachineAction::CREATE,
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
                    'Action "create" on machine "' . self::MACHINE_ID . '" failed',
                    0,
                    new MachineActionFailedException(
                        self::MACHINE_ID,
                        MachineAction::CREATE,
                        new Stack([
                            new HttpException(
                                self::MACHINE_ID,
                                MachineAction::CREATE,
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
            'invalid droplet data (empty)' => [
                'httpResponse' => new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    (string) json_encode([])
                ),
                'expectedException' => new UnrecoverableMessageHandlingException(
                    'Action "create" on machine "' . self::MACHINE_ID . '" failed',
                    0,
                    new MachineActionFailedException(
                        self::MACHINE_ID,
                        MachineAction::CREATE,
                        new Stack([
                            new InvalidEntityResponseException(
                                MachineProvider::DIGITALOCEAN,
                                [],
                                self::MACHINE_ID,
                                MachineAction::CREATE,
                                new InvalidEntityDataException('droplet', []),
                            ),
                        ])
                    )
                ),
            ],
            'invalid droplet data (lacking fields)' => [
                'httpResponse' => new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    (string) json_encode([
                        'droplet' => [
                            'id' => 123,
                        ],
                    ])
                ),
                'expectedException' => new UnrecoverableMessageHandlingException(
                    'Action "create" on machine "' . self::MACHINE_ID . '" failed',
                    0,
                    new MachineActionFailedException(
                        self::MACHINE_ID,
                        MachineAction::CREATE,
                        new Stack([
                            new InvalidEntityResponseException(
                                MachineProvider::DIGITALOCEAN,
                                ['id' => '123'],
                                self::MACHINE_ID,
                                MachineAction::CREATE,
                                new InvalidEntityDataException('droplet', ['id' => '123']),
                            ),
                        ])
                    )
                ),
            ],
            'unknown http client error' => [
                'httpResponse' => new TransferException(),
                'expectedException' => new UnrecoverableMessageHandlingException(
                    'Action "create" on machine "' . self::MACHINE_ID . '" failed',
                    0,
                    new MachineActionFailedException(
                        self::MACHINE_ID,
                        MachineAction::CREATE,
                        new Stack([
                            new HttpClientException(
                                self::MACHINE_ID,
                                MachineAction::CREATE,
                                new TransferException(),
                            ),
                        ])
                    )
                ),
            ],
        ];
    }

    private function createHandler(MachineRequestDispatcher $machineRequestDispatcher): CreateMachineHandler
    {
        $machineManager = self::getContainer()->get(MachineManager::class);
        \assert($machineManager instanceof MachineManager);

        $machineUpdater = self::getContainer()->get(MachineUpdater::class);
        \assert($machineUpdater instanceof MachineUpdater);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        return new CreateMachineHandler(
            $machineManager,
            $machineRequestDispatcher,
            $machineUpdater,
            $machineRepository,
        );
    }
}
