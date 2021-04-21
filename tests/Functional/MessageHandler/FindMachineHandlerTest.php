<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Message\CheckMachineIsActive;
use App\Message\CreateMachine;
use App\Message\FindMachine;
use App\MessageHandler\FindMachineHandler;
use App\Model\DigitalOcean\RemoteMachine;
use App\Model\MachineActionProperties;
use App\Model\MachineActionPropertiesInterface;
use App\Services\ExceptionLogger;
use App\Services\MachineActionPropertiesFactory;
use App\Services\MachineRequestFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\Services\MockExceptionLogger;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Http\Message\ResponseInterface;
use webignition\BasilWorkerManager\PersistenceBundle\Entity\Machine;
use webignition\BasilWorkerManager\PersistenceBundle\Entity\MachineProvider;
use webignition\BasilWorkerManager\PersistenceBundle\Services\Store\MachineProviderStore;
use webignition\BasilWorkerManager\PersistenceBundle\Services\Store\MachineStore;
use webignition\BasilWorkerManagerInterfaces\MachineActionInterface;
use webignition\BasilWorkerManagerInterfaces\MachineInterface;
use webignition\BasilWorkerManagerInterfaces\MachineProviderInterface;
use webignition\BasilWorkerManagerInterfaces\ProviderInterface;
use webignition\ObjectReflector\ObjectReflector;

class FindMachineHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

    private FindMachineHandler $handler;
    private MessengerAsserter $messengerAsserter;
    private MockHandler $mockHandler;
    private MachineStore $machineStore;
    private MachineProviderStore $machineProviderStore;
    private MachineActionPropertiesFactory $machineActionPropertiesFactory;
    private MachineRequestFactory $machineRequestFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::$container->get(FindMachineHandler::class);
        \assert($handler instanceof FindMachineHandler);
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

        $machineActionPropertiesFactory = self::$container->get(MachineActionPropertiesFactory::class);
        \assert($machineActionPropertiesFactory instanceof MachineActionPropertiesFactory);
        $this->machineActionPropertiesFactory = $machineActionPropertiesFactory;

        $machineRequestFactory = self::$container->get(MachineRequestFactory::class);
        \assert($machineRequestFactory instanceof MachineRequestFactory);
        $this->machineRequestFactory = $machineRequestFactory;
    }

    /**
     * @dataProvider invokeSuccessDataProvider
     *
     * @param MachineActionPropertiesInterface[] $messageOnSuccessCollection
     * @param MachineActionPropertiesInterface[] $messageOnFailureCollection
     * @param ResponseInterface[] $apiResponses
     * @param object[] $expectedQueuedMessages
     */
    public function testInvokeSuccess(
        MachineInterface $machine,
        ?MachineProviderInterface $machineProvider,
        array $messageOnSuccessCollection,
        array $messageOnFailureCollection,
        array $apiResponses,
        MachineInterface $expectedMachine,
        MachineProviderInterface $expectedMachineProvider,
        int $expectedQueueCount,
        array $expectedQueuedMessages
    ): void {
        $this->setExceptionLoggerOnHandler(
            (new MockExceptionLogger())
                ->withoutLogCall()
                ->getMock()
        );

        $this->mockHandler->append(...$apiResponses);
        $this->machineStore->store($machine);

        if ($machineProvider instanceof MachineProviderInterface) {
            $this->machineProviderStore->store($machineProvider);
        }

        $message = new FindMachine(self::MACHINE_ID, $messageOnSuccessCollection, $messageOnFailureCollection);
        ($this->handler)($message);

        self::assertEquals($expectedMachine, $this->machineStore->find(self::MACHINE_ID));
        self::assertEquals($expectedMachineProvider, $this->machineProviderStore->find(self::MACHINE_ID));

        $this->messengerAsserter->assertQueueCount($expectedQueueCount);
        self::assertCount($expectedQueueCount, $expectedQueuedMessages);

        foreach ($expectedQueuedMessages as $expectedIndex => $expectedMessage) {
            $this->messengerAsserter->assertMessageAtPositionEquals(
                $expectedIndex,
                $expectedMessage
            );
        }
    }

    /**
     * @return array[]
     */
    public function invokeSuccessDataProvider(): array
    {
        $upNewDropletEntity = new DropletEntity([
            'status' => RemoteMachine::STATE_NEW,
            'networks' => (object) [
                'v4' => [
                    (object) [
                        'ip_address' => '10.0.0.1',
                        'type' => 'public',
                    ],
                ],
            ],
        ]);

        $nonDigitalOceanMachineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        ObjectReflector::setProperty(
            $nonDigitalOceanMachineProvider,
            MachineProvider::class,
            'name',
            'different'
        );

        return [
            'remote machine found and updated, no existing provider' => [
                'machine' => new Machine(self::MACHINE_ID, MachineInterface::STATE_FIND_RECEIVED),
                'machineProvider' => null,
                'messageOnSuccessCollection' => [
                    $this->getMachineActionPropertiesFactory()->createForCheckIsActive(self::MACHINE_ID),
                ],
                'messageOnFailureCollection' => [],
                'apiResponses' => [
                    HttpResponseFactory::fromDropletEntityCollection([$upNewDropletEntity])
                ],
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    MachineInterface::STATE_UP_STARTED,
                    [
                        '10.0.0.1',
                    ]
                ),
                'expectedMachineProvider' => new MachineProvider(
                    self::MACHINE_ID,
                    ProviderInterface::NAME_DIGITALOCEAN
                ),
                'expectedQueueCount' => 1,
                'expectedQueuedMessages' => [
                    new CheckMachineIsActive(
                        self::MACHINE_ID,
                        [
                            new MachineActionProperties(
                                MachineActionInterface::ACTION_GET,
                                self::MACHINE_ID
                            )
                        ]
                    ),
                ],
            ],
            'remote machine found and updated, has existing provider' => [
                'machine' => new Machine(self::MACHINE_ID, MachineInterface::STATE_FIND_RECEIVED),
                'machineProvider' => $nonDigitalOceanMachineProvider,
                'messageOnSuccessCollection' => [
                    $this->getMachineActionPropertiesFactory()->createForCheckIsActive(self::MACHINE_ID),
                ],
                'messageOnFailureCollection' => [],
                'apiResponses' => [
                    HttpResponseFactory::fromDropletEntityCollection([$upNewDropletEntity])
                ],
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    MachineInterface::STATE_UP_STARTED,
                    [
                        '10.0.0.1',
                    ]
                ),
                'expectedMachineProvider' => new MachineProvider(
                    self::MACHINE_ID,
                    ProviderInterface::NAME_DIGITALOCEAN
                ),
                'expectedQueueCount' => 1,
                'expectedQueuedMessages' => [
                    new CheckMachineIsActive(
                        self::MACHINE_ID,
                        [
                            new MachineActionProperties(
                                MachineActionInterface::ACTION_GET,
                                self::MACHINE_ID
                            )
                        ]
                    ),
                ],
            ],
            'remote machine not found, create requested' => [
                'machine' => new Machine(self::MACHINE_ID, MachineInterface::STATE_FIND_RECEIVED),
                'machineProvider' => $nonDigitalOceanMachineProvider,
                'messageOnSuccessCollection' => [],
                'messageOnFailureCollection' => [
                    $this->getMachineActionPropertiesFactory()->createForCreate(self::MACHINE_ID)
                ],
                'apiResponses' => [
                    HttpResponseFactory::fromDropletEntityCollection([])
                ],
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    MachineInterface::STATE_FIND_NOT_FOUND
                ),
                'expectedMachineProvider' => new MachineProvider(
                    self::MACHINE_ID,
                    ProviderInterface::NAME_DIGITALOCEAN
                ),
                'expectedQueueCount' => 1,
                'expectedQueuedMessages' => [
                    new CreateMachine(
                        self::MACHINE_ID,
                        [
                            $this->getMachineActionPropertiesFactory()->createForCheckIsActive(self::MACHINE_ID),
                        ]
                    ),
                ],
            ],
        ];
    }

    public function testInvokeMachineEntityMissing(): void
    {
        $machineId = 'invalid machine id';

        $this->setExceptionLoggerOnHandler(
            (new MockExceptionLogger())
                ->withoutLogCall()
                ->getMock()
        );

        ($this->handler)(new FindMachine($machineId));

        self::assertNull($this->machineStore->find($machineId));
        self::assertNull($this->machineProviderStore->find($machineId));
    }

    public function testInvokeRemoteMachineNotFoundRetrying(): void
    {
        $this->messengerAsserter->assertQueueIsEmpty();

        $this->setExceptionLoggerOnHandler(
            (new MockExceptionLogger())
                ->withoutLogCall()
                ->getMock()
        );

        $machine = new Machine(self::MACHINE_ID, MachineInterface::STATE_FIND_RECEIVED);
        $this->machineStore->store($machine);

        $this->mockHandler->append(new Response(503));

        $message = $this->machineRequestFactory->create(
            $this->machineActionPropertiesFactory->createForFind(self::MACHINE_ID)
        );
        \assert($message instanceof FindMachine);
        self::assertInstanceOf(FindMachine::class, $message);

        ($this->handler)($message);

        $this->messengerAsserter->assertQueueCount(1);
        $this->messengerAsserter->assertMessageAtPositionEquals(0, $message->incrementRetryCount());

        self::assertSame(MachineInterface::STATE_FIND_FINDING, $machine->getState());
        self::assertNull($this->machineProviderStore->find(self::MACHINE_ID));
        self::assertEquals($machine, $this->machineStore->find(self::MACHINE_ID));
    }

    public function testInvokeRemoteMachineNotFindable(): void
    {
        $this->messengerAsserter->assertQueueIsEmpty();

        $this->setExceptionLoggerOnHandler(
            (new MockExceptionLogger())
                ->withLogCall(new HttpException(
                    self::MACHINE_ID,
                    MachineActionInterface::ACTION_GET,
                    new RuntimeException('Service Unavailable', 503)
                ))
                ->getMock()
        );

        $machine = new Machine(self::MACHINE_ID, MachineInterface::STATE_FIND_RECEIVED);
        $this->machineStore->store($machine);

        $this->mockHandler->append(new Response(503));

        $message = new FindMachine(self::MACHINE_ID);
        $message = $message->incrementRetryCount();
        $message = $message->incrementRetryCount();
        $message = $message->incrementRetryCount();

        ($this->handler)($message);

        $this->messengerAsserter->assertQueueIsEmpty();

        self::assertSame(MachineInterface::STATE_FIND_NOT_FINDABLE, $machine->getState());
        self::assertNull($this->machineProviderStore->find(self::MACHINE_ID));
        self::assertEquals($machine, $this->machineStore->find(self::MACHINE_ID));
    }

    public function testInvokeRemoteMachineNotFound(): void
    {
        $this->messengerAsserter->assertQueueIsEmpty();

        $this->setExceptionLoggerOnHandler(
            (new MockExceptionLogger())
                ->withoutLogCall()
                ->getMock()
        );

        $machine = new Machine(self::MACHINE_ID, MachineInterface::STATE_FIND_RECEIVED);
        $this->machineStore->store($machine);

        $this->mockHandler->append(HttpResponseFactory::fromDropletEntityCollection([]));

        $message = new FindMachine(self::MACHINE_ID);

        ($this->handler)($message);

        $this->messengerAsserter->assertQueueIsEmpty();

        self::assertSame(MachineInterface::STATE_FIND_NOT_FOUND, $machine->getState());
        self::assertNull($this->machineProviderStore->find(self::MACHINE_ID));
        self::assertEquals($machine, $this->machineStore->find(self::MACHINE_ID));
    }

    private function setExceptionLoggerOnHandler(ExceptionLogger $exceptionLogger): void
    {
        ObjectReflector::setProperty(
            $this->handler,
            FindMachineHandler::class,
            'exceptionLogger',
            $exceptionLogger
        );
    }

    private function getMachineActionPropertiesFactory(): MachineActionPropertiesFactory
    {
        return new MachineActionPropertiesFactory();
    }
}