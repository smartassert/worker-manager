<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Enum\MachineState;
use App\Event\MachineCreatedEvent;
use App\Exception\MachineActionFailedException;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\HttpClientException;
use App\Exception\MachineProvider\InvalidEntityResponseException;
use App\Exception\Stack;
use App\Message\CheckMachineIsActive;
use App\Message\CreateMachine;
use App\MessageHandler\CreateMachineHandler;
use App\Model\DigitalOcean\RemoteMachine;
use App\Repository\MachineRepository;
use App\Services\MachineManager\DigitalOcean\Entity\Droplet;
use App\Services\MachineManager\DigitalOcean\Entity\Error;
use App\Services\MachineManager\DigitalOcean\Entity\Network;
use App\Services\MachineManager\DigitalOcean\Entity\NetworkCollection;
use App\Services\MachineManager\DigitalOcean\Exception\ApiLimitExceededException as DOApiLimitExceededException;
use App\Services\MachineManager\DigitalOcean\Exception\AuthenticationException as DOAuthenticationException;
use App\Services\MachineManager\DigitalOcean\Exception\ErrorException;
use App\Services\MachineManager\DigitalOcean\Exception\InvalidEntityDataException;
use App\Services\MachineManager\DigitalOcean\Request\CreateDropletRequest;
use App\Tests\AbstractBaseFunctionalTestCase;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EventRecorder;
use App\Tests\Services\TestMachineRequestFactory;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use SmartAssert\DigitalOceanDropletConfiguration\Configuration;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

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
        self::assertCount(1, new \ReflectionClass($handler::class)->getAttributes(AsMessageHandler::class));
    }

    public function testInvokeSuccess(): void
    {
        self::assertSame([], $this->machine->getIpAddresses());

        $mockHandler = self::getContainer()->get('app.tests.httpclient.mocked.handler');
        \assert($mockHandler instanceof MockHandler);

        $ipAddresses = ['10.0.0.1', '127.0.0.1'];

        $remoteMachineId = rand();
        \assert($remoteMachineId > 1 && $remoteMachineId < PHP_INT_MAX);

        $remoteMachineStatus = RemoteMachine::STATE_NEW;

        $mockHandler->append(new Response(
            200,
            [
                'Content-Type' => 'application/json',
            ],
            (string) json_encode([
                'droplet' => [
                    'id' => $remoteMachineId,
                    'status' => $remoteMachineStatus,
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

        self::assertNull($this->machine->getProvider());

        $handler = self::getContainer()->get(CreateMachineHandler::class);
        \assert($handler instanceof CreateMachineHandler);

        ($handler)($message);

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);

        $machineCreatedEvents = $eventRecorder->all(MachineCreatedEvent::class);
        $machineCreatedEvent = $machineCreatedEvents[0];

        self::assertEquals(
            new MachineCreatedEvent(
                $this->machine,
                new RemoteMachine(
                    new Droplet(
                        $remoteMachineId,
                        $remoteMachineStatus,
                        new NetworkCollection([
                            new Network(
                                $ipAddresses[0],
                                true,
                                4,
                            ),
                            new Network(
                                $ipAddresses[1],
                                true,
                                4,
                            ),
                        ]),
                    )
                ),
            ),
            $machineCreatedEvent,
        );

        self::assertSame(MachineState::UP_STARTED, $this->machine->getState());
        self::assertSame($ipAddresses, $this->machine->getIpAddresses());
        self::assertSame(MachineProvider::DIGITALOCEAN, $this->machine->getProvider());

        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);

        $dispatchedEnvelopes = $messengerTransport->getSent();
        self::assertCount(1, $dispatchedEnvelopes);

        $dispatchedEnvelope = $dispatchedEnvelopes[0];
        $dispatchedMessage = $dispatchedEnvelope->getMessage();

        self::assertInstanceOf(CheckMachineIsActive::class, $dispatchedMessage);
        self::assertSame($this->machine->getId(), $dispatchedMessage->getMachineId());
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
                                    new Error(429, 'too_many_requests', 'API Rate limit exceeded'),
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
                                    new Error(500, $internalServerErrorId, $internalServerErrorMessage),
                                    new CreateDropletRequest(
                                        new Configuration(
                                            name: 'test-worker-' . self::MACHINE_ID,
                                            size: 's-1vcpu-1gb',
                                            image: 'ubuntu-22-04-x64',
                                            region: 'lon1',
                                            sshKeys: [
                                                123456,
                                            ],
                                            tags: [
                                                'test-worker-' . self::MACHINE_ID,
                                            ],
                                        ),
                                    ),
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
                                    new Error(503, $serviceUnavailableErrorId, $serviceUnavailableErrorMessage),
                                    new CreateDropletRequest(
                                        new Configuration(
                                            name: 'test-worker-' . self::MACHINE_ID,
                                            size: 's-1vcpu-1gb',
                                            image: 'ubuntu-22-04-x64',
                                            region: 'lon1',
                                            sshKeys: [
                                                123456,
                                            ],
                                            tags: [
                                                'test-worker-' . self::MACHINE_ID,
                                            ],
                                        ),
                                    ),
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
}
