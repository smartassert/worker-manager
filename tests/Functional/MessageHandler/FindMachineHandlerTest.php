<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Enum\MachineAction;
use App\Enum\MachineState;
use App\Exception\MachineNotFindableException;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Message\FindMachine;
use App\Message\MachineRequestInterface;
use App\MessageHandler\FindMachineHandler;
use App\Model\DigitalOcean\RemoteMachine;
use App\Model\ProviderInterface;
use App\Repository\MachineProviderRepository;
use App\Repository\MachineRepository;
use App\Services\MachineManager;
use App\Services\MachineNameFactory;
use App\Services\MachineRequestDispatcher;
use App\Services\MachineUpdater;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Proxy\DigitalOceanV2\Api\DropletApiProxy;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\TestMachineRequestFactory;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\RuntimeException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use webignition\ObjectReflector\ObjectReflector;

class FindMachineHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

    private MachineProviderRepository $machineProviderRepository;
    private DropletApiProxy $dropletApiProxy;
    private MachineNameFactory $machineNameFactory;
    private MachineRepository $machineRepository;
    private TestMachineRequestFactory $machineRequestFactory;

    protected function setUp(): void
    {
        parent::setUp();

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

        $machineRequestFactory = self::getContainer()->get(TestMachineRequestFactory::class);
        \assert($machineRequestFactory instanceof TestMachineRequestFactory);
        $this->machineRequestFactory = $machineRequestFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
            $entityRemover->removeAllForEntity(MachineProvider::class);
        }
    }

    public function testHandlerExistsInContainerAndIsAMessageHandler(): void
    {
        $handler = self::getContainer()->get(FindMachineHandler::class);
        self::assertInstanceOf(FindMachineHandler::class, $handler);
        self::assertCount(1, (new \ReflectionClass($handler::class))->getAttributes(AsMessageHandler::class));
    }

    /**
     * @dataProvider invokeSuccessDataProvider
     *
     * @param DropletEntity[] $expectedGetAllOutcome
     * @param callable(FindMachine $message): MachineRequestInterface[] $expectedMachineRequestCollectionCreator
     * @param callable(TestMachineRequestFactory $factory): FindMachine $messageCreator
     */
    public function testInvokeSuccess(
        Machine $machine,
        ?MachineProvider $machineProvider,
        array $expectedGetAllOutcome,
        Machine $expectedMachine,
        MachineProvider $expectedMachineProvider,
        callable $messageCreator,
        callable $expectedMachineRequestCollectionCreator,
    ): void {
        $expectedMachineName = $this->machineNameFactory->create($machine->getId());

        $this->dropletApiProxy->withGetAllCall($expectedMachineName, $expectedGetAllOutcome);

        $this->machineRepository->add($machine);

        if ($machineProvider instanceof MachineProvider) {
            $this->machineProviderRepository->add($machineProvider);
        }

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
        self::assertEquals($expectedMachineProvider, $this->machineProviderRepository->find(self::MACHINE_ID));
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
                'machine' => new Machine(self::MACHINE_ID, MachineState::FIND_RECEIVED),
                'machineProvider' => null,
                'expectedGetAllOutcome' => [$upNewDropletEntity],
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    MachineState::UP_STARTED,
                    [
                        '10.0.0.1',
                    ]
                ),
                'expectedMachineProvider' => new MachineProvider(
                    self::MACHINE_ID,
                    ProviderInterface::NAME_DIGITALOCEAN
                ),
                'messageCreator' => function (TestMachineRequestFactory $factory) {
                    return $factory->createFind(
                        self::MACHINE_ID,
                        [$factory->createCheckIsActive(self::MACHINE_ID)],
                        []
                    );
                },
                'expectedMachineRequestCollectionCreator' => function (FindMachine $message): array {
                    return $message->getOnSuccessCollection();
                },
            ],
            'remote machine found and updated, has existing provider' => [
                'machine' => new Machine(self::MACHINE_ID, MachineState::FIND_RECEIVED),
                'machineProvider' => $nonDigitalOceanMachineProvider,
                'expectedGetAllOutcome' => [$upNewDropletEntity],
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    MachineState::UP_STARTED,
                    [
                        '10.0.0.1',
                    ]
                ),
                'expectedMachineProvider' => new MachineProvider(
                    self::MACHINE_ID,
                    ProviderInterface::NAME_DIGITALOCEAN
                ),
                'messageCreator' => function (TestMachineRequestFactory $factory) {
                    return $factory->createFind(
                        self::MACHINE_ID,
                        [$factory->createCheckIsActive(self::MACHINE_ID)],
                        []
                    );
                },
                'expectedMachineRequestCollectionCreator' => function (FindMachine $message): array {
                    return $message->getOnSuccessCollection();
                },
            ],
            'remote machine not found, create requested' => [
                'machine' => new Machine(self::MACHINE_ID, MachineState::FIND_RECEIVED),
                'machineProvider' => $nonDigitalOceanMachineProvider,
                'expectedGetAllOutcome' => [],
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    MachineState::FIND_NOT_FOUND
                ),
                'expectedMachineProvider' => new MachineProvider(
                    self::MACHINE_ID,
                    ProviderInterface::NAME_DIGITALOCEAN
                ),
                'messageCreator' => function (TestMachineRequestFactory $factory) {
                    return $factory->createFind(
                        self::MACHINE_ID,
                        [$factory->createCheckIsActive(self::MACHINE_ID)],
                        []
                    );
                },
                'expectedMachineRequestCollectionCreator' => function (FindMachine $message): array {
                    return $message->getOnFailureCollection();
                },
            ],
            'remote machine found, re-dispatch self' => [
                'machine' => new Machine(self::MACHINE_ID, MachineState::FIND_RECEIVED),
                'machineProvider' => $nonDigitalOceanMachineProvider,
                'expectedGetAllOutcome' => [$upNewDropletEntity],
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    MachineState::UP_STARTED,
                    [
                        '10.0.0.1',
                    ]
                ),
                'expectedMachineProvider' => new MachineProvider(
                    self::MACHINE_ID,
                    ProviderInterface::NAME_DIGITALOCEAN
                ),
                'messageCreator' => function (TestMachineRequestFactory $factory) {
                    $message = $factory->createFind(
                        self::MACHINE_ID,
                        [],
                        []
                    );

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
        self::assertNull($this->machineProviderRepository->find($machineId));
    }

    /**
     * @dataProvider invokeThrowsExceptionDataProvider
     */
    public function testInvokeThrowsException(\Exception $vendorException, \Exception $expectedException): void
    {
        $machine = new Machine(self::MACHINE_ID, MachineState::FIND_RECEIVED);
        $this->machineRepository->add($machine);

        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        $this->machineProviderRepository->add($machineProvider);

        $this->dropletApiProxy->withGetAllCall($this->machineNameFactory->create($machine->getId()), $vendorException);

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
    public function invokeThrowsExceptionDataProvider(): array
    {
        $http401Exception = new RuntimeException('Unauthorized', 401);

        $authenticationException = new AuthenticationException(
            self::MACHINE_ID,
            MachineAction::GET,
            $http401Exception
        );

        $http503Exception = new RuntimeException('Service Unavailable', 503);

        $serviceUnavailableException = new HttpException(
            self::MACHINE_ID,
            MachineAction::GET,
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
            $this->machineProviderRepository
        );
    }
}
