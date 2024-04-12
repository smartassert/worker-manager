<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Enum\MachineState;
use App\Exception\MachineActionFailedException;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\NoDigitalOceanClientException;
use App\Message\FindMachine;
use App\Message\MachineRequestInterface;
use App\MessageHandler\FindMachineHandler;
use App\Model\DigitalOcean\RemoteMachine;
use App\Repository\MachineRepository;
use App\Services\ExceptionFactory\MachineProvider\ExceptionFactory;
use App\Services\MachineManager\MachineManager;
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

class FindMachineHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

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
     * @param DropletEntity[]                                           $expectedGetAllOutcome
     * @param callable(FindMachine $message): MachineRequestInterface[] $expectedMachineRequestCollectionCreator
     * @param callable(TestMachineRequestFactory $factory): FindMachine $messageCreator
     */
    public function testInvokeSuccess(
        Machine $machine,
        array $expectedGetAllOutcome,
        Machine $expectedMachine,
        callable $messageCreator,
        callable $expectedMachineRequestCollectionCreator,
    ): void {
        $expectedMachineName = $this->machineNameFactory->create($machine->getId());

        $this->dropletApiProxy->withGetAllCall($expectedMachineName, $expectedGetAllOutcome);

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

        return [
            'remote machine found and updated, no existing provider' => [
                'machine' => new Machine(self::MACHINE_ID, MachineState::FIND_RECEIVED),
                'expectedGetAllOutcome' => [$upNewDropletEntity],
                'expectedMachine' => (function () {
                    $machine = new Machine(
                        self::MACHINE_ID,
                        MachineState::UP_STARTED,
                        [
                            '10.0.0.1',
                        ]
                    );
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
                    $machine = new Machine(self::MACHINE_ID, MachineState::FIND_RECEIVED);
                    $machine->setProvider(MachineProvider::DIGITALOCEAN);

                    return $machine;
                })(),
                'expectedGetAllOutcome' => [$upNewDropletEntity],
                'expectedMachine' => (function () {
                    $machine = new Machine(
                        self::MACHINE_ID,
                        MachineState::UP_STARTED,
                        [
                            '10.0.0.1',
                        ]
                    );
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
                    $machine = new Machine(self::MACHINE_ID, MachineState::FIND_RECEIVED);
                    $machine->setProvider(MachineProvider::DIGITALOCEAN);

                    return $machine;
                })(),
                'expectedGetAllOutcome' => [],
                'expectedMachine' => (function () {
                    $machine = new Machine(self::MACHINE_ID, MachineState::FIND_NOT_FOUND);
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
                    $machine = new Machine(self::MACHINE_ID, MachineState::FIND_RECEIVED);
                    $machine->setProvider(MachineProvider::DIGITALOCEAN);

                    return $machine;
                })(),
                'expectedGetAllOutcome' => [$upNewDropletEntity],
                'expectedMachine' => (function () {
                    $machine = new Machine(
                        self::MACHINE_ID,
                        MachineState::UP_STARTED,
                        [
                            '10.0.0.1',
                        ]
                    );
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

    /**
     * @dataProvider invokeThrowsExceptionDataProvider
     */
    public function testInvokeThrowsException(\Exception $vendorException, \Exception $expectedException): void
    {
        $machine = new Machine(self::MACHINE_ID, MachineState::FIND_RECEIVED);
        $this->machineRepository->add($machine);

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
            MachineProvider::DIGITALOCEAN,
            self::MACHINE_ID,
            MachineAction::FIND,
            [$http401Exception]
        );

        $http503Exception = new RuntimeException('Service Unavailable', 503);

        $serviceUnavailableException = new HttpException(
            self::MACHINE_ID,
            MachineAction::FIND,
            $http503Exception
        );

        $machineNotFindableAuthenticationException = new MachineActionFailedException(
            self::MACHINE_ID,
            MachineAction::FIND,
            [
                $authenticationException,
            ]
        );

        $machineNotFindableServiceUnavailableException = new MachineActionFailedException(
            self::MACHINE_ID,
            MachineAction::FIND,
            [
                $serviceUnavailableException,
            ]
        );

        return [
            'HTTP 401' => [
                'vendorException' => new NoDigitalOceanClientException([
                    $http401Exception,
                ]),
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

        $exceptionFactory = self::getContainer()->get(ExceptionFactory::class);
        \assert($exceptionFactory instanceof ExceptionFactory);

        return new FindMachineHandler(
            $machineManager,
            $machineUpdater,
            $machineRequestDispatcher,
            $this->machineRepository,
            $exceptionFactory,
        );
    }
}
