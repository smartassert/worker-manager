<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Entity\MessageState;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\UnsupportedProviderException;
use App\Message\GetMachine;
use App\MessageHandler\GetMachineHandler;
use App\Model\DigitalOcean\RemoteMachine;
use App\Model\ProviderInterface;
use App\Services\Entity\Store\MachineProviderStore;
use App\Services\Entity\Store\MachineStore;
use App\Services\ExceptionLogger;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\Services\MockExceptionLogger;
use App\Tests\Services\Asserter\MessageStateEntityAsserter;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Http\Message\ResponseInterface;
use webignition\ObjectReflector\ObjectReflector;

class GetMachineHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';
    private const REMOTE_ID = 123;

    private GetMachineHandler $handler;
    private MessengerAsserter $messengerAsserter;
    private MockHandler $mockHandler;
    private MachineStore $machineStore;
    private MachineProviderStore $machineProviderStore;
    private MessageStateEntityAsserter $messageStateEntityAsserter;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::$container->get(GetMachineHandler::class);
        \assert($handler instanceof GetMachineHandler);
        $this->handler = $handler;

        $mockHandler = self::$container->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $messengerAsserter = self::$container->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $machineStore = self::$container->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $this->machineStore = $machineStore;

        $machineProviderStore = self::$container->get(MachineProviderStore::class);
        \assert($machineProviderStore instanceof MachineProviderStore);
        $this->machineProviderStore = $machineProviderStore;

        $messageStateEntityAsserter = self::$container->get(MessageStateEntityAsserter::class);
        \assert($messageStateEntityAsserter instanceof MessageStateEntityAsserter);
        $this->messageStateEntityAsserter = $messageStateEntityAsserter;
    }

    /**
     * @dataProvider invokeSuccessDataProvider
     */
    public function testInvokeSuccess(
        ResponseInterface $apiResponse,
        Machine $machine,
        Machine $expectedMachine,
    ): void {
        $this->setExceptionLoggerOnHandler(
            (new MockExceptionLogger())
                ->withoutLogCall()
                ->getMock()
        );

        $this->mockHandler->append($apiResponse);

        $this->machineStore->store($machine);

        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        $this->machineProviderStore->store($machineProvider);

        $expectedMachineProvider = clone $machineProvider;

        $message = new GetMachine('id0', $machine->getId());
        ($this->handler)($message);

        self::assertEquals($expectedMachine, $machine);
        self::assertEquals($expectedMachineProvider, $machineProvider);

        $this->messengerAsserter->assertQueueIsEmpty();
        $this->messageStateEntityAsserter->assertCount(0);
    }

    /**
     * @return array[]
     */
    public function invokeSuccessDataProvider(): array
    {
        $ipAddresses = [
            '10.0.0.1',
            '127.0.0.1',
        ];

        $createdDropletEntity = new DropletEntity([
            'id' => self::REMOTE_ID,
            'status' => RemoteMachine::STATE_NEW,
        ]);

        $upNewDropletEntity = new DropletEntity([
            'id' => self::REMOTE_ID,
            'status' => RemoteMachine::STATE_NEW,
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
        ]);

        $upActiveDropletEntity = new DropletEntity([
            'id' => self::REMOTE_ID,
            'status' => RemoteMachine::STATE_ACTIVE,
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
        ]);

        return [
            'updated within initial remote id and initial remote state' => [
                'apiResponse' => HttpResponseFactory::fromDropletEntityCollection([$createdDropletEntity]),
                'machine' => new Machine(self::MACHINE_ID),
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    Machine::STATE_UP_STARTED
                ),
            ],
            'updated within initial ip addresses' => [
                'apiResponse' => HttpResponseFactory::fromDropletEntityCollection([$upNewDropletEntity]),
                'machine' => new Machine(self::MACHINE_ID, Machine::STATE_UP_STARTED),
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    Machine::STATE_UP_STARTED,
                    $ipAddresses
                ),
            ],
            'updated within active remote state' => [
                'apiResponse' => HttpResponseFactory::fromDropletEntityCollection([$upActiveDropletEntity]),
                'machine' => new Machine(
                    self::MACHINE_ID,
                    Machine::STATE_UP_STARTED,
                    $ipAddresses
                ),
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    Machine::STATE_UP_ACTIVE,
                    $ipAddresses
                ),
            ],
        ];
    }

    /**
     * @dataProvider invokeRetryingDataProvider
     *
     * @param array<int, GetMachine> $expectedDispatchedMessages
     */
    public function testInvokeRetrying(
        ResponseInterface $apiResponse,
        Machine $machine,
        MachineProvider $machineProvider,
        array $expectedDispatchedMessages,
    ): void {
        $this->setExceptionLoggerOnHandler(
            (new MockExceptionLogger())
                ->withoutLogCall()
                ->getMock()
        );

        $this->mockHandler->append($apiResponse);

        $this->machineStore->store($machine);
        $this->machineProviderStore->store($machineProvider);

        $expectedMachine = clone $machine;
        $expectedMachineProvider = clone $machineProvider;

        $message = new GetMachine('id0', $machine->getId());
        ($this->handler)($message);

        self::assertEquals($expectedMachine, $machine);
        self::assertEquals($expectedMachineProvider, $machineProvider);

        $this->messengerAsserter->assertQueueCount(count($expectedDispatchedMessages));
        foreach ($expectedDispatchedMessages as $index => $expectedMessage) {
            $this->messengerAsserter->assertMessageAtPositionEquals($index, $expectedMessage);
        }

        $this->messageStateEntityAsserter->assertCount(1);
        $this->messageStateEntityAsserter->assertHas(new MessageState($message->getUniqueId()));
    }

    /**
     * @return array[]
     */
    public function invokeRetryingDataProvider(): array
    {
        $machine = new Machine(self::MACHINE_ID, Machine::STATE_CREATE_RECEIVED);
        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);

        return [
            'HTTP 503, requires retry' => [
                'httpFixtures' => new Response(503),
                'machine' => $machine,
                'machineProvider' => $machineProvider,
                'expectedDispatchedMessages' => [
                    (new GetMachine(
                        'id0',
                        self::MACHINE_ID
                    ))->incrementRetryCount()
                ],
            ],
        ];
    }

    public function testInvokeThrowsAuthenticationExceptionWithoutRetry(): void
    {
        $this->mockHandler->append(new Response(401));

        $machine = new Machine(self::MACHINE_ID, Machine::STATE_CREATE_RECEIVED);
        $this->machineStore->store($machine);

        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        $this->machineProviderStore->store($machineProvider);

        $message = new GetMachine('id0', $machine->getId());

        $expectedLoggedException = new AuthenticationException(
            $machine->getId(),
            $message->getAction(),
            new RuntimeException('Unauthorized', 401)
        );

        $exceptionLogger = (new MockExceptionLogger())
            ->withLogCall($expectedLoggedException)
            ->getMock()
        ;

        $this->setExceptionLoggerOnHandler($exceptionLogger);

        ($this->handler)($message);

        $this->messengerAsserter->assertQueueIsEmpty();
        $this->messageStateEntityAsserter->assertCount(0);
    }

    /**
     * @dataProvider invokeThrowsHttpExceptionWithoutRetryDataProvider
     */
    public function testInvokeThrowsHttpExceptionWithoutRetry(
        ResponseInterface $apiResponse,
        int $retryCount,
        RuntimeException $expectedRemoteException
    ): void {
        $this->mockHandler->append($apiResponse);

        $machine = new Machine(self::MACHINE_ID, Machine::STATE_CREATE_RECEIVED);
        $this->machineStore->store($machine);

        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        $this->machineProviderStore->store($machineProvider);

        $message = new GetMachine('id0', $machine->getId());
        ObjectReflector::setProperty($message, $message::class, 'retryCount', $retryCount);

        $expectedLoggedException = new HttpException(
            $machine->getId(),
            $message->getAction(),
            $expectedRemoteException
        );

        $exceptionLogger = (new MockExceptionLogger())
            ->withLogCall($expectedLoggedException)
            ->getMock()
        ;

        $this->setExceptionLoggerOnHandler($exceptionLogger);

        ($this->handler)($message);

        $this->messengerAsserter->assertQueueIsEmpty();
        $this->messageStateEntityAsserter->assertCount(0);
    }

    /**
     * @return array[]
     */
    public function invokeThrowsHttpExceptionWithoutRetryDataProvider(): array
    {
        return [
            'HTTP 503, does not require retry, retry limit reached' => [
                'apiResponse' => new Response(503),
                'retryCount' => 10,
                'expectedWrappedLoggedException' => new RuntimeException('Service Unavailable', 503),
            ],
        ];
    }

    public function testInvokeThrowsUnknownProviderException(): void
    {
        $invalidProvider = 'invalid';
        $expectedLoggedException = new UnsupportedProviderException($invalidProvider);

        $machine = new Machine(self::MACHINE_ID, Machine::STATE_CREATE_RECEIVED);
        $this->machineStore->store($machine);

        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        ObjectReflector::setProperty($machineProvider, MachineProvider::class, 'provider', $invalidProvider);
        $this->machineProviderStore->store($machineProvider);

        $exceptionLogger = (new MockExceptionLogger())
            ->withLogCall($expectedLoggedException)
            ->getMock()
        ;

        $this->setExceptionLoggerOnHandler($exceptionLogger);

        $message = new GetMachine('id0', $machine->getId());
        ($this->handler)($message);

        $this->messengerAsserter->assertQueueIsEmpty();
        $this->messageStateEntityAsserter->assertCount(0);
    }

    public function testHandleThrowsUnknownRemoteMachineException(): void
    {
        $this->mockHandler->append(new Response(404));

        $machine = new Machine(self::MACHINE_ID, Machine::STATE_CREATE_RECEIVED);
        $this->machineStore->store($machine);

        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        $this->machineProviderStore->store($machineProvider);

        $message = new GetMachine('id0', $machine->getId());
        ObjectReflector::setProperty($message, $message::class, 'retryCount', 11);

        ($this->handler)($message);

        self::assertSame(Machine::STATE_CREATE_RECEIVED, $machine->getState());
    }

    private function setExceptionLoggerOnHandler(ExceptionLogger $exceptionLogger): void
    {
        ObjectReflector::setProperty(
            $this->handler,
            GetMachineHandler::class,
            'exceptionLogger',
            $exceptionLogger
        );
    }
}
