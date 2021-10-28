<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\CreateFailure;
use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Entity\MessageState;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\Exception;
use App\Exception\UnsupportedProviderException;
use App\Message\CreateMachine;
use App\MessageHandler\CreateMachineHandler;
use App\Model\DigitalOcean\RemoteMachine;
use App\Model\MachineActionInterface;
use App\Model\ProviderInterface;
use App\Services\Entity\Store\MachineProviderStore;
use App\Services\Entity\Store\MachineStore;
use App\Services\MachineManager;
use App\Services\MachineRequestFactory;
use App\Services\RequestIdFactoryInterface;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\Services\MockExceptionLogger;
use App\Tests\Mock\Services\MockMachineManager;
use App\Tests\Services\Asserter\MessageStateEntityAsserter;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\HttpResponseFactory;
use App\Tests\Services\SequentialRequestIdFactory;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\ApiLimitExceededException as VendorApiLimitExceededExceptionAlias;
use DigitalOceanV2\Exception\InvalidArgumentException;
use DigitalOceanV2\Exception\RuntimeException;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Handler\MockHandler;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use SmartAssert\InvokableLogger\ExceptionLogger;
use webignition\ObjectReflector\ObjectReflector;

class CreateMachineHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'machine id';

    private CreateMachineHandler $handler;
    private MessengerAsserter $messengerAsserter;
    private MockHandler $mockHandler;
    private Machine $machine;
    private MachineProvider $machineProvider;
    private EntityManagerInterface $entityManager;
    private MachineRequestFactory $machineRequestFactory;
    private SequentialRequestIdFactory $requestIdFactory;
    private MessageStateEntityAsserter $messageStateEntityAsserter;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::$container->get(CreateMachineHandler::class);
        \assert($handler instanceof CreateMachineHandler);
        $this->handler = $handler;

        $machineStore = self::$container->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $this->machine = new Machine(self::MACHINE_ID);
        $machineStore->store($this->machine);

        $machineProviderStore = self::$container->get(MachineProviderStore::class);
        \assert($machineProviderStore instanceof MachineProviderStore);
        $this->machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        $machineProviderStore->store($this->machineProvider);

        $messengerAsserter = self::$container->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $mockHandler = self::$container->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $entityManager = self::$container->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;

        $machineRequestFactory = self::$container->get(MachineRequestFactory::class);
        \assert($machineRequestFactory instanceof MachineRequestFactory);
        $this->machineRequestFactory = $machineRequestFactory;

        $requestIdFactory = self::$container->get(RequestIdFactoryInterface::class);
        \assert($requestIdFactory instanceof SequentialRequestIdFactory);
        $this->requestIdFactory = $requestIdFactory;

        $messageStateEntityAsserter = self::$container->get(MessageStateEntityAsserter::class);
        \assert($messageStateEntityAsserter instanceof MessageStateEntityAsserter);
        $this->messageStateEntityAsserter = $messageStateEntityAsserter;
    }

    public function testInvokeSuccess(): void
    {
        self::assertSame([], ObjectReflector::getProperty($this->machine, 'ip_addresses'));

        $dropletData = [
            'id' => 123,
            'networks' => (object) [
                'v4' => [
                    (object) [
                        'ip_address' => '10.0.0.1',
                        'type' => 'public',
                    ],
                    (object) [
                        'ip_address' => '127.0.0.1',
                        'type' => 'public',
                    ],
                ],
            ],
            'status' => RemoteMachine::STATE_NEW,
        ];

        $expectedDropletEntity = new DropletEntity($dropletData);
        $this->mockHandler->append(HttpResponseFactory::fromDropletEntity($expectedDropletEntity));

        $message = $this->machineRequestFactory->createCreate(self::MACHINE_ID);

        ($this->handler)($message);

        $this->requestIdFactory->reset(1);

        $expectedRequest = $this->machineRequestFactory->createCheckIsActive(self::MACHINE_ID);
        $expectedRemoteMachine = new RemoteMachine($expectedDropletEntity);
        $this->messengerAsserter->assertQueueCount(1);
        $this->messengerAsserter->assertMessageAtPositionEquals(0, $expectedRequest);

        self::assertSame($expectedRemoteMachine->getState(), $this->machine->getState());
        self::assertSame(
            $expectedRemoteMachine->getIpAddresses(),
            ObjectReflector::getProperty($this->machine, 'ip_addresses')
        );

        $this->messageStateEntityAsserter->assertCount(1);
        $this->messageStateEntityAsserter->assertHas(new MessageState($expectedRequest->getUniqueId()));
    }

    public function testHandleWithUnsupportedProviderException(): void
    {
        $exception = \Mockery::mock(UnsupportedProviderException::class);

        $message = new CreateMachine('id0', self::MACHINE_ID);

        $machineManager = (new MockMachineManager())
            ->withCreateCallThrowingException($this->machineProvider, $exception)
            ->getMock()
        ;

        $exceptionLogger = (new MockExceptionLogger())
            ->withLogCall($exception)
            ->getMock()
        ;

        $this->prepareHandler($machineManager, $exceptionLogger);

        ($this->handler)($message);

        $this->messengerAsserter->assertQueueIsEmpty();
        $this->messageStateEntityAsserter->assertCount(0);

        self::assertSame(Machine::STATE_CREATE_FAILED, $this->machine->getState());
        self::assertSame(0, $message->getRetryCount());

        $createFailure = $this->entityManager->find(CreateFailure::class, $this->machine->getId());
        self::assertEquals(
            new CreateFailure(
                self::MACHINE_ID,
                CreateFailure::CODE_UNSUPPORTED_PROVIDER,
                CreateFailure::REASON_UNSUPPORTED_PROVIDER
            ),
            $createFailure
        );
    }

    /**
     * @dataProvider invokeWithExceptionWithRetryDataProvider
     */
    public function testInvokeExceptionWithRetry(\Throwable $previous, int $retryCount): void
    {
        $message = $this->machineRequestFactory->createCreate(self::MACHINE_ID);
        ObjectReflector::setProperty($message, $message::class, 'retryCount', $retryCount);

        $exception = new Exception(self::MACHINE_ID, $message->getAction(), $previous);

        $machineManager = (new MockMachineManager())
            ->withCreateCallThrowingException($this->machineProvider, $exception)
            ->getMock()
        ;

        $exceptionLogger = (new MockExceptionLogger())
            ->withoutLogCall()
            ->getMock()
        ;

        $this->prepareHandler($machineManager, $exceptionLogger);

        ($this->handler)($message);

        $expectedMessage = $message->incrementRetryCount();

        $this->messengerAsserter->assertQueueCount(1);
        $this->messengerAsserter->assertMessageAtPositionEquals(0, $expectedMessage);

        self::assertNotSame(Machine::STATE_CREATE_FAILED, $this->machine->getState());

        $this->messageStateEntityAsserter->assertCount(1);
        $this->messageStateEntityAsserter->assertHas(new MessageState($expectedMessage->getUniqueId()));
    }

    /**
     * @return array[]
     */
    public function invokeWithExceptionWithRetryDataProvider(): array
    {
        return [
            'requires retry, retry limit not reached (0)' => [
                'previous' => \Mockery::mock(InvalidArgumentException::class),
                'retryCount' => 0,
            ],
            'requires retry, retry limit not reached (1)' => [
                'previous' => \Mockery::mock(InvalidArgumentException::class),
                'retryCount' => 1,
            ],
            'requires retry, retry limit not reached (2)' => [
                'previous' => \Mockery::mock(InvalidArgumentException::class),
                'retryCount' => 2,
            ],
        ];
    }

    /**
     * @dataProvider handleWithExceptionWithoutRetryDataProvider
     */
    public function testHandleExceptionWithoutRetry(
        \Exception $exception,
        int $retryCount,
        CreateFailure $expectedCreateFailure
    ): void {
        $message = new CreateMachine('id0', self::MACHINE_ID);
        ObjectReflector::setProperty($message, $message::class, 'retryCount', $retryCount);

        $machineManager = (new MockMachineManager())
            ->withCreateCallThrowingException($this->machineProvider, $exception)
            ->getMock()
        ;

        $exceptionLogger = (new MockExceptionLogger())
            ->withLogCall($exception)
            ->getMock()
        ;

        $this->prepareHandler($machineManager, $exceptionLogger);

        ($this->handler)($message);

        $this->messengerAsserter->assertQueueIsEmpty();
        $this->messageStateEntityAsserter->assertCount(0);

        self::assertSame(Machine::STATE_CREATE_FAILED, $this->machine->getState());

        $createFailure = $this->entityManager->find(CreateFailure::class, $this->machine->getId());
        self::assertEquals($expectedCreateFailure, $createFailure);
    }

    /**
     * @return array[]
     */
    public function handleWithExceptionWithoutRetryDataProvider(): array
    {
        return [
            'does not require retry' => [
                'exception' => new ApiLimitExceededException(
                    123,
                    self::MACHINE_ID,
                    MachineActionInterface::ACTION_GET,
                    new VendorApiLimitExceededExceptionAlias()
                ),
                'retryCount' => 0,
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    CreateFailure::CODE_API_LIMIT_EXCEEDED,
                    CreateFailure::REASON_API_LIMIT_EXCEEDED,
                    [
                        'reset-timestamp' => 123,
                    ]
                ),
            ],
            'requires retry, retry limit reached (3)' => [
                'exception' => new HttpException(
                    self::MACHINE_ID,
                    MachineActionInterface::ACTION_GET,
                    new RuntimeException('Internal Server Error', 500)
                ),
                'retryCount' => 3,
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    CreateFailure::CODE_HTTP_ERROR,
                    CreateFailure::REASON_HTTP_ERROR,
                    [
                        'status-code' => 500,
                    ]
                ),
            ],
        ];
    }

    private function prepareHandler(MachineManager $machineManager, ExceptionLogger $exceptionLogger): void
    {
        $this->setMachineManagerOnHandler($machineManager);
        $this->setExceptionLoggerOnHandler($exceptionLogger);
    }

    private function setMachineManagerOnHandler(MachineManager $machineManager): void
    {
        ObjectReflector::setProperty(
            $this->handler,
            CreateMachineHandler::class,
            'machineManager',
            $machineManager
        );
    }

    private function setExceptionLoggerOnHandler(ExceptionLogger $exceptionLogger): void
    {
        ObjectReflector::setProperty(
            $this->handler,
            CreateMachineHandler::class,
            'exceptionLogger',
            $exceptionLogger
        );
    }
}
