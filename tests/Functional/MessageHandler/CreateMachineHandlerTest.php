<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Entity\MessageState;
use App\Exception\MachineProvider\Exception;
use App\Exception\MachineProvider\UnknownRemoteMachineException;
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
use App\Tests\Mock\Services\MockMachineManager;
use App\Tests\Services\Asserter\MessageStateEntityAsserter;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\HttpResponseFactory;
use App\Tests\Services\SequentialRequestIdFactory;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\InvalidArgumentException;
use GuzzleHttp\Handler\MockHandler;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
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
    private MachineRequestFactory $machineRequestFactory;
    private SequentialRequestIdFactory $requestIdFactory;
    private MessageStateEntityAsserter $messageStateEntityAsserter;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(CreateMachineHandler::class);
        \assert($handler instanceof CreateMachineHandler);
        $this->handler = $handler;

        $machineStore = self::getContainer()->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $this->machine = new Machine(self::MACHINE_ID);
        $machineStore->store($this->machine);

        $machineProviderStore = self::getContainer()->get(MachineProviderStore::class);
        \assert($machineProviderStore instanceof MachineProviderStore);
        $this->machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        $machineProviderStore->store($this->machineProvider);

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $mockHandler = self::getContainer()->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $machineRequestFactory = self::getContainer()->get(MachineRequestFactory::class);
        \assert($machineRequestFactory instanceof MachineRequestFactory);
        $this->machineRequestFactory = $machineRequestFactory;

        $requestIdFactory = self::getContainer()->get(RequestIdFactoryInterface::class);
        \assert($requestIdFactory instanceof SequentialRequestIdFactory);
        $this->requestIdFactory = $requestIdFactory;

        $messageStateEntityAsserter = self::getContainer()->get(MessageStateEntityAsserter::class);
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

    /**
     * @dataProvider handleWithUnrecoverableExceptionDataProvider
     */
    public function testHandleWithUnrecoverableException(\Exception $exception, \Exception $expectedException): void
    {
        $message = new CreateMachine('id0', self::MACHINE_ID);

        $machineManager = (new MockMachineManager())
            ->withCreateCallThrowingException($this->machineProvider, $exception)
            ->getMock()
        ;

        $this->setMachineManagerOnHandler($machineManager);

        $this->expectExceptionObject($expectedException);

        ($this->handler)($message);
    }

    /**
     * @return array<mixed>
     */
    public function handleWithUnrecoverableExceptionDataProvider(): array
    {
        $unsupportedProviderException = new UnsupportedProviderException(
            ProviderInterface::NAME_DIGITALOCEAN
        );

        $unknownRemoveMachineException = new UnknownRemoteMachineException(
            ProviderInterface::NAME_DIGITALOCEAN,
            'machine id',
            MachineActionInterface::ACTION_GET,
            new \Exception()
        );

        return [
            UnsupportedProviderException::class => [
                'exception' => $unsupportedProviderException,
                'expectedException' => new UnrecoverableMessageHandlingException(
                    $unsupportedProviderException->getMessage(),
                    $unsupportedProviderException->getCode(),
                    $unsupportedProviderException
                ),
            ],
            UnknownRemoteMachineException::class . ' with action ' . MachineActionInterface::ACTION_GET => [
                'exception' => $unknownRemoveMachineException,
                'expectedException' => new UnrecoverableMessageHandlingException(
                    $unknownRemoveMachineException->getMessage(),
                    $unknownRemoveMachineException->getCode(),
                    $unknownRemoveMachineException
                ),
            ],
        ];
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

        $this->setMachineManagerOnHandler($machineManager);

        $this->expectExceptionObject($exception);

        ($this->handler)($message);
    }

    /**
     * @return array<mixed>
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

    private function setMachineManagerOnHandler(MachineManager $machineManager): void
    {
        ObjectReflector::setProperty(
            $this->handler,
            CreateMachineHandler::class,
            'machineManager',
            $machineManager
        );
    }
}
