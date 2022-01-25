<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Entity\MessageState;
use App\Exception\MachineNotFindableException;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Message\FindMachine;
use App\Message\MachineRequestInterface;
use App\MessageHandler\FindMachineHandler;
use App\Model\DigitalOcean\RemoteMachine;
use App\Model\MachineActionInterface;
use App\Model\ProviderInterface;
use App\Services\Entity\Store\MachineProviderStore;
use App\Services\Entity\Store\MachineStore;
use App\Services\MachineRequestFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\Asserter\MessageStateEntityAsserter;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\HttpResponseFactory;
use App\Tests\Services\SequentialRequestIdFactory;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
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
    private MachineRequestFactory $machineRequestFactory;
    private MessageStateEntityAsserter $messageStateEntityAsserter;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(FindMachineHandler::class);
        \assert($handler instanceof FindMachineHandler);
        $this->handler = $handler;

        $mockHandler = self::getContainer()->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $machineStore = self::getContainer()->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $this->machineStore = $machineStore;

        $machineProviderStore = self::getContainer()->get(MachineProviderStore::class);
        \assert($machineProviderStore instanceof MachineProviderStore);
        $this->machineProviderStore = $machineProviderStore;

        $machineRequestFactory = self::getContainer()->get(MachineRequestFactory::class);
        \assert($machineRequestFactory instanceof MachineRequestFactory);
        $this->machineRequestFactory = $machineRequestFactory;

        $messageStateEntityAsserter = self::getContainer()->get(MessageStateEntityAsserter::class);
        \assert($messageStateEntityAsserter instanceof MessageStateEntityAsserter);
        $this->messageStateEntityAsserter = $messageStateEntityAsserter;
    }

    /**
     * @dataProvider invokeSuccessDataProvider
     *
     * @param MachineRequestInterface[] $messageOnSuccessCollection
     * @param MachineRequestInterface[] $messageOnFailureCollection
     * @param ResponseInterface[]       $apiResponses
     * @param object[]                  $expectedQueuedMessages
     */
    public function testInvokeSuccess(
        Machine $machine,
        ?MachineProvider $machineProvider,
        array $messageOnSuccessCollection,
        array $messageOnFailureCollection,
        bool $reDispatchOnSuccess,
        array $apiResponses,
        Machine $expectedMachine,
        MachineProvider $expectedMachineProvider,
        int $expectedQueueCount,
        array $expectedQueuedMessages
    ): void {
        $this->mockHandler->append(...$apiResponses);
        $this->machineStore->store($machine);

        if ($machineProvider instanceof MachineProvider) {
            $this->machineProviderStore->store($machineProvider);
        }

        $message = $this->machineRequestFactory->createFind(
            self::MACHINE_ID,
            $messageOnSuccessCollection,
            $messageOnFailureCollection
        );
        $message = $message->withReDispatchOnSuccess($reDispatchOnSuccess);

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

        $this->messageStateEntityAsserter->assertCount(1);
        $this->messageStateEntityAsserter->assertHas(new MessageState($message->getUniqueId()));
    }

    /**
     * @return array<mixed>
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
                'machine' => new Machine(self::MACHINE_ID, Machine::STATE_FIND_RECEIVED),
                'machineProvider' => null,
                'messageOnSuccessCollection' => [
                    $this->getMachineRequestFactory()->createCheckIsActive(self::MACHINE_ID),
                ],
                'messageOnFailureCollection' => [],
                'reDispatchOnSuccess' => false,
                'apiResponses' => [
                    HttpResponseFactory::fromDropletEntityCollection([$upNewDropletEntity])
                ],
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    Machine::STATE_UP_STARTED,
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
                    $this->getMachineRequestFactory()->createCheckIsActive(self::MACHINE_ID),
                ],
            ],
            'remote machine found and updated, has existing provider' => [
                'machine' => new Machine(self::MACHINE_ID, Machine::STATE_FIND_RECEIVED),
                'machineProvider' => $nonDigitalOceanMachineProvider,
                'messageOnSuccessCollection' => [
                    $this->getMachineRequestFactory()->createCheckIsActive(self::MACHINE_ID),
                ],
                'messageOnFailureCollection' => [],
                'reDispatchOnSuccess' => false,
                'apiResponses' => [
                    HttpResponseFactory::fromDropletEntityCollection([$upNewDropletEntity])
                ],
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    Machine::STATE_UP_STARTED,
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
                    $this->getMachineRequestFactory()->createCheckIsActive(self::MACHINE_ID),
                ],
            ],
            'remote machine not found, create requested' => [
                'machine' => new Machine(self::MACHINE_ID, Machine::STATE_FIND_RECEIVED),
                'machineProvider' => $nonDigitalOceanMachineProvider,
                'messageOnSuccessCollection' => [],
                'messageOnFailureCollection' => [
                    $this->getMachineRequestFactory()->createCreate(self::MACHINE_ID)
                ],
                'reDispatchOnSuccess' => false,
                'apiResponses' => [
                    HttpResponseFactory::fromDropletEntityCollection([])
                ],
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    Machine::STATE_FIND_NOT_FOUND
                ),
                'expectedMachineProvider' => new MachineProvider(
                    self::MACHINE_ID,
                    ProviderInterface::NAME_DIGITALOCEAN
                ),
                'expectedQueueCount' => 1,
                'expectedQueuedMessages' => [
                    $this->getMachineRequestFactory()->createCreate(self::MACHINE_ID),
                ],
            ],
            'remote machine found, re-dispatch self' => [
                'machine' => new Machine(self::MACHINE_ID, Machine::STATE_FIND_RECEIVED),
                'machineProvider' => $nonDigitalOceanMachineProvider,
                'messageOnSuccessCollection' => [],
                'messageOnFailureCollection' => [],
                'reDispatchOnSuccess' => true,
                'apiResponses' => [
                    HttpResponseFactory::fromDropletEntityCollection([$upNewDropletEntity])
                ],
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    Machine::STATE_UP_STARTED,
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
                    $this->getMachineRequestFactory()->createFind(self::MACHINE_ID)
                        ->withReDispatchOnSuccess(true)
                        ->incrementRetryCount(),
                ],
            ],
        ];
    }

    public function testInvokeMachineEntityMissing(): void
    {
        $machineId = 'invalid machine id';

        ($this->handler)(new FindMachine('id0', $machineId));

        self::assertNull($this->machineStore->find($machineId));
        self::assertNull($this->machineProviderStore->find($machineId));
    }

    /**
     * @dataProvider invokeThrowsExceptionDataProvider
     */
    public function testInvokeThrowsException(
        ?ResponseInterface $httpFixture,
        Machine $machine,
        MachineProvider $machineProvider,
        \Exception $expectedException
    ): void {
        if ($httpFixture instanceof ResponseInterface) {
            $this->mockHandler->append($httpFixture);
        }

        $this->machineStore->store($machine);
        $this->machineProviderStore->store($machineProvider);

        $message = new FindMachine('id0', $machine->getId());

        try {
            ($this->handler)($message);
            $this->fail($expectedException::class . ' not thrown');
        } catch (\Exception $exception) {
            self::assertEquals($expectedException, $exception);
        }

        self::assertSame(Machine::STATE_FIND_FINDING, $machine->getState());
    }

    /**
     * @return array<mixed>
     */
    public function invokeThrowsExceptionDataProvider(): array
    {
        $machine = new Machine(self::MACHINE_ID, Machine::STATE_FIND_RECEIVED);
        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);

        $authenticationException = new AuthenticationException(
            self::MACHINE_ID,
            MachineActionInterface::ACTION_GET,
            new RuntimeException('Unauthorized', 401)
        );

        $serviceUnavailableException = new HttpException(
            self::MACHINE_ID,
            MachineActionInterface::ACTION_GET,
            new RuntimeException('Service Unavailable', 503)
        );

        $machineNotFindableAuthenticationException = new MachineNotFindableException(
            self::MACHINE_ID,
            [
                $authenticationException,
            ]
        );

        $machineNotFindableServiceUnavailableException = new MachineNotFindableException(
            self::MACHINE_ID,
            [
                $serviceUnavailableException,
            ]
        );

        return [
            'HTTP 401' => [
                'httpFixture' => new Response(401),
                'machine' => $machine,
                'machineProvider' => $machineProvider,
                'expectedException' => new UnrecoverableMessageHandlingException(
                    $machineNotFindableAuthenticationException->getMessage(),
                    $machineNotFindableAuthenticationException->getCode(),
                    $machineNotFindableAuthenticationException
                ),
            ],
            'HTTP 503' => [
                'httpFixture' => new Response(503),
                'machine' => $machine,
                'machineProvider' => $machineProvider,
                'expectedException' => new UnrecoverableMessageHandlingException(
                    $machineNotFindableServiceUnavailableException->getMessage(),
                    $machineNotFindableServiceUnavailableException->getCode(),
                    $machineNotFindableServiceUnavailableException
                ),
            ],
        ];
    }

    private function getMachineRequestFactory(): MachineRequestFactory
    {
        return new MachineRequestFactory(
            new SequentialRequestIdFactory()
        );
    }
}
