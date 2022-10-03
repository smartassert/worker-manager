<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Exception\MachineNotFindableException;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Message\FindMachine;
use App\Message\MachineRequestInterface;
use App\MessageHandler\FindMachineHandler;
use App\Model\DigitalOcean\RemoteMachine;
use App\Model\MachineActionInterface;
use App\Model\ProviderInterface;
use App\Repository\MachineProviderRepository;
use App\Repository\MachineRepository;
use App\Services\MachineNameFactory;
use App\Services\MachineRequestFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Proxy\DigitalOceanV2\Api\DropletApiProxy;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\SequentialRequestIdFactory;
use App\Tests\Services\TestMachineRequestFactory;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\RuntimeException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use webignition\ObjectReflector\ObjectReflector;

class FindMachineHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

    private FindMachineHandler $handler;
    private MessengerAsserter $messengerAsserter;
    private MachineProviderRepository $machineProviderRepository;
    private DropletApiProxy $dropletApiProxy;
    private MachineNameFactory $machineNameFactory;
    private MachineRepository $machineRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(FindMachineHandler::class);
        \assert($handler instanceof FindMachineHandler);
        $this->handler = $handler;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $this->machineRepository = $machineRepository;

        $machineProviderRepository = self::getContainer()->get(MachineProviderRepository::class);
        \assert($machineProviderRepository instanceof MachineProviderRepository);
        $this->machineProviderRepository = $machineProviderRepository;

        $dropletApiProxy = self::getContainer()->get(DropletApiProxy::class);
        \assert($dropletApiProxy instanceof DropletApiProxy);
        $this->dropletApiProxy = $dropletApiProxy;

        $machineNameFactory = self::getContainer()->get(MachineNameFactory::class);
        \assert($machineNameFactory instanceof MachineNameFactory);
        $this->machineNameFactory = $machineNameFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
            $entityRemover->removeAllForEntity(MachineProvider::class);
        }
    }

    /**
     * @dataProvider invokeSuccessDataProvider
     *
     * @param MachineRequestInterface[] $messageOnSuccessCollection
     * @param MachineRequestInterface[] $messageOnFailureCollection
     * @param DropletEntity[]           $expectedGetAllOutcome
     * @param object[]                  $expectedQueuedMessages
     */
    public function testInvokeSuccess(
        Machine $machine,
        ?MachineProvider $machineProvider,
        array $messageOnSuccessCollection,
        array $messageOnFailureCollection,
        bool $reDispatchOnSuccess,
        array $expectedGetAllOutcome,
        Machine $expectedMachine,
        MachineProvider $expectedMachineProvider,
        int $expectedQueueCount,
        array $expectedQueuedMessages,
    ): void {
        $expectedMachineName = $this->machineNameFactory->create($machine->getId());

        $this->dropletApiProxy->withGetAllCall($expectedMachineName, $expectedGetAllOutcome);

        $this->machineRepository->add($machine);

        if ($machineProvider instanceof MachineProvider) {
            $this->machineProviderRepository->add($machineProvider);
        }

        $message = $this->createMachineRequestFactory()->createFind(
            self::MACHINE_ID,
            $messageOnSuccessCollection,
            $messageOnFailureCollection
        );
        $message = $message->withReDispatchOnSuccess($reDispatchOnSuccess);

        ($this->handler)($message);

        self::assertEquals($expectedMachine, $this->machineRepository->find(self::MACHINE_ID));
        self::assertEquals($expectedMachineProvider, $this->machineProviderRepository->find(self::MACHINE_ID));

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
                    $this->createMachineRequestFactory()->createCheckIsActive(self::MACHINE_ID),
                ],
                'messageOnFailureCollection' => [],
                'reDispatchOnSuccess' => false,
                'expectedGetAllOutcome' => [$upNewDropletEntity],
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
                    $this->createMachineRequestFactory()->createCheckIsActive(self::MACHINE_ID),
                ],
            ],
            'remote machine found and updated, has existing provider' => [
                'machine' => new Machine(self::MACHINE_ID, Machine::STATE_FIND_RECEIVED),
                'machineProvider' => $nonDigitalOceanMachineProvider,
                'messageOnSuccessCollection' => [
                    $this->createMachineRequestFactory()->createCheckIsActive(self::MACHINE_ID),
                ],
                'messageOnFailureCollection' => [],
                'reDispatchOnSuccess' => false,
                'expectedGetAllOutcome' => [$upNewDropletEntity],
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
                    $this->createMachineRequestFactory()->createCheckIsActive(self::MACHINE_ID),
                ],
            ],
            'remote machine not found, create requested' => [
                'machine' => new Machine(self::MACHINE_ID, Machine::STATE_FIND_RECEIVED),
                'machineProvider' => $nonDigitalOceanMachineProvider,
                'messageOnSuccessCollection' => [],
                'messageOnFailureCollection' => [
                    $this->createMachineRequestFactory()->createCreate(self::MACHINE_ID)
                ],
                'reDispatchOnSuccess' => false,
                'expectedGetAllOutcome' => [],
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
                    $this->createMachineRequestFactory()->createCreate(self::MACHINE_ID),
                ],
            ],
            'remote machine found, re-dispatch self' => [
                'machine' => new Machine(self::MACHINE_ID, Machine::STATE_FIND_RECEIVED),
                'machineProvider' => $nonDigitalOceanMachineProvider,
                'messageOnSuccessCollection' => [],
                'messageOnFailureCollection' => [],
                'reDispatchOnSuccess' => true,
                'expectedGetAllOutcome' => [$upNewDropletEntity],
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
                    $this->createMachineRequestFactory()->createFind(self::MACHINE_ID)
                        ->withReDispatchOnSuccess(true),
                ],
            ],
        ];
    }

    public function testInvokeMachineEntityMissing(): void
    {
        $machineId = 'invalid machine id';

        ($this->handler)(new FindMachine('id0', $machineId));

        self::assertNull($this->machineRepository->find($machineId));
        self::assertNull($this->machineProviderRepository->find($machineId));
    }

    /**
     * @dataProvider invokeThrowsExceptionDataProvider
     */
    public function testInvokeThrowsException(\Exception $vendorException, \Exception $expectedException): void
    {
        $machine = new Machine(self::MACHINE_ID, Machine::STATE_FIND_RECEIVED);
        $this->machineRepository->add($machine);

        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        $this->machineProviderRepository->add($machineProvider);

        $this->dropletApiProxy->withGetAllCall($this->machineNameFactory->create($machine->getId()), $vendorException);

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
        $http401Exception = new RuntimeException('Unauthorized', 401);

        $authenticationException = new AuthenticationException(
            self::MACHINE_ID,
            MachineActionInterface::ACTION_GET,
            $http401Exception
        );

        $http503Exception = new RuntimeException('Service Unavailable', 503);

        $serviceUnavailableException = new HttpException(
            self::MACHINE_ID,
            MachineActionInterface::ACTION_GET,
            $http503Exception
        );

        $machineNotFindableAuthenticationException = new MachineNotFindableException(self::MACHINE_ID, [
            $authenticationException,
        ]);

        $machineNotFindableServiceUnavailableException = new MachineNotFindableException(self::MACHINE_ID, [
            $serviceUnavailableException,
        ]);

        return [
            'HTTP 401' => [
                'vendorException' => $http401Exception,
                'expectedException' => new UnrecoverableMessageHandlingException(
                    $machineNotFindableAuthenticationException->getMessage(),
                    $machineNotFindableAuthenticationException->getCode(),
                    $machineNotFindableAuthenticationException
                ),
            ],
            'HTTP 503' => [
                'vendorException' => $http503Exception,
                'expectedException' => new UnrecoverableMessageHandlingException(
                    $machineNotFindableServiceUnavailableException->getMessage(),
                    $machineNotFindableServiceUnavailableException->getCode(),
                    $machineNotFindableServiceUnavailableException
                ),
            ],
        ];
    }

    private function createMachineRequestFactory(): TestMachineRequestFactory
    {
        return new TestMachineRequestFactory(
            new MachineRequestFactory(
                new SequentialRequestIdFactory()
            )
        );
    }
}
