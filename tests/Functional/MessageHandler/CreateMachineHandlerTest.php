<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Enum\MachineAction;
use App\Enum\MachineState;
use App\Exception\MachineActionFailedException;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\UnknownRemoteMachineException;
use App\Message\CreateMachine;
use App\MessageHandler\CreateMachineHandler;
use App\Model\DigitalOcean\RemoteMachine;
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
use DigitalOceanV2\Exception\ResourceNotFoundException;
use DigitalOceanV2\Exception\RuntimeException as VendorRuntimeException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Ulid;
use webignition\ObjectReflector\ObjectReflector;

class CreateMachineHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'machine id';

    private CreateMachineHandler $handler;
    private Machine $machine;
    private DropletApiProxy $dropletApiProxy;
    private string $machineName;
    private TestMachineRequestFactory $machineRequestFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(CreateMachineHandler::class);
        \assert($handler instanceof CreateMachineHandler);
        $this->handler = $handler;

        $dropletApiProxy = self::getContainer()->get(DropletApiProxy::class);
        \assert($dropletApiProxy instanceof DropletApiProxy);
        $this->dropletApiProxy = $dropletApiProxy;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
            $entityRemover->removeAllForEntity(MachineProvider::class);
        }

        $machineProviderRepository = self::getContainer()->get(MachineProviderRepository::class);
        \assert($machineProviderRepository instanceof MachineProviderRepository);
        $machineProvider = new MachineProvider(self::MACHINE_ID, RemoteMachine::TYPE);
        $machineProviderRepository->add($machineProvider);

        $machineId = (string) new Ulid();
        \assert('' !== $machineId);
        $this->machine = new Machine($machineId, MachineState::CREATE_RECEIVED);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $machineRepository->add($this->machine);

        $machineNameFactory = self::getContainer()->get(MachineNameFactory::class);
        \assert($machineNameFactory instanceof MachineNameFactory);
        $this->machineName = $machineNameFactory->create($this->machine->getId());

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
        self::assertSame([], ObjectReflector::getProperty($this->machine, 'ip_addresses'));

        $dropletId = 123;

        $dropletData = [
            'id' => $dropletId,
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
        $this->dropletApiProxy->prepareCreateCall($this->machineName, $expectedDropletEntity);

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

        $machineProviderRepository = self::getContainer()->get(MachineProviderRepository::class);
        \assert($machineProviderRepository instanceof MachineProviderRepository);
        self::assertNull($machineProviderRepository->find($this->machine->getId()));

        $handler = $this->createHandler($machineRequestDispatcher);
        ($handler)($message);

        $expectedRemoteMachine = new RemoteMachine($expectedDropletEntity);

        self::assertSame($expectedRemoteMachine->getState(), $this->machine->getState());
        self::assertSame(
            $expectedRemoteMachine->getIpAddresses(),
            ObjectReflector::getProperty($this->machine, 'ip_addresses')
        );

        self::assertEquals(
            new MachineProvider($this->machine->getId(), RemoteMachine::TYPE),
            $machineProviderRepository->find($this->machine->getId())
        );
    }

    /**
     * @dataProvider invokeThrowsExceptionDataProvider
     *
     * @param callable(Machine): \Throwable $vendorExceptionCreator
     * @param callable(Machine): \Throwable $expectedExceptionCreator
     */
    public function testInvokeThrowsException(
        callable $vendorExceptionCreator,
        callable $expectedExceptionCreator,
    ): void {
        $this->dropletApiProxy->prepareCreateCall($this->machineName, $vendorExceptionCreator($this->machine));

        $message = new CreateMachine('id0', $this->machine->getId());
        $expectedException = $expectedExceptionCreator($this->machine);

        try {
            ($this->handler)($message);
            $this->fail($expectedException::class . ' not thrown');
        } catch (\Exception $exception) {
            self::assertEquals($expectedException, $exception);
        }

        self::assertSame(MachineState::CREATE_REQUESTED, $this->machine->getState());
    }

    /**
     * @return array<mixed>
     */
    public function invokeThrowsExceptionDataProvider(): array
    {
        return [
            'HTTP 401' => [
                'vendorExceptionCreator' => function () {
                    return new VendorRuntimeException('Unauthorized', 401);
                },
                'expectedExceptionCreator' => function (Machine $machine) {
                    return new UnrecoverableMessageHandlingException(
                        'Action "create" on machine "' . $machine->getId() . '" failed',
                        0,
                        new MachineActionFailedException(
                            $machine->getId(),
                            MachineAction::CREATE,
                            [
                                new AuthenticationException(
                                    $machine->getId(),
                                    MachineAction::CREATE,
                                    new VendorRuntimeException('Unauthorized', 401)
                                ),
                            ]
                        )
                    );
                },
            ],
            'HTTP 404' => [
                'vendorExceptionCreator' => function () {
                    return new ResourceNotFoundException('Not Found', 404);
                },
                'expectedExceptionCreator' => function (Machine $machine) {
                    return new UnrecoverableMessageHandlingException(
                        'Action "create" on machine "' . $machine->getId() . '" failed',
                        0,
                        new MachineActionFailedException(
                            $machine->getId(),
                            MachineAction::CREATE,
                            [
                                new UnknownRemoteMachineException(
                                    RemoteMachine::TYPE,
                                    $machine->getId(),
                                    MachineAction::CREATE,
                                    new ResourceNotFoundException('Not Found', 404),
                                ),
                            ]
                        )
                    );
                },
            ],
            'HTTP 429' => [
                'vendorExceptionCreator' => function (Machine $machine) {
                    return new ApiLimitExceededException(
                        1400000,
                        $machine->getId(),
                        MachineAction::CREATE,
                        new \DigitalOceanV2\Exception\ApiLimitExceededException('Too Many Requests', 429)
                    );
                },
                'expectedExceptionCreator' => function (Machine $machine) {
                    return new UnrecoverableMessageHandlingException(
                        'Action "create" on machine "' . $machine->getId() . '" failed',
                        0,
                        new MachineActionFailedException(
                            $machine->getId(),
                            MachineAction::CREATE,
                            [
                                new ApiLimitExceededException(
                                    1400000,
                                    $machine->getId(),
                                    MachineAction::CREATE,
                                    new \DigitalOceanV2\Exception\ApiLimitExceededException('Too Many Requests', 429)
                                ),
                            ]
                        )
                    );
                },
            ],
            'HTTP 503' => [
                'vendorExceptionCreator' => function () {
                    return new VendorRuntimeException('Service Unavailable', 503);
                },
                'expectedExceptionCreator' => function (Machine $machine) {
                    return new UnrecoverableMessageHandlingException(
                        'Action "create" on machine "' . $machine->getId() . '" failed',
                        0,
                        new MachineActionFailedException(
                            $machine->getId(),
                            MachineAction::CREATE,
                            [
                                new HttpException(
                                    $machine->getId(),
                                    MachineAction::CREATE,
                                    new VendorRuntimeException('Service Unavailable', 503)
                                ),
                            ]
                        )
                    );
                },
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

        $machineProviderRepository = self::getContainer()->get(MachineProviderRepository::class);
        \assert($machineProviderRepository instanceof MachineProviderRepository);

        return new CreateMachineHandler(
            $machineManager,
            $machineRequestDispatcher,
            $machineUpdater,
            $machineRepository,
            $machineProviderRepository,
        );
    }
}
