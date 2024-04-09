<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Enum\MachineAction;
use App\Enum\MachineProvider as MachineProviderEnum;
use App\Enum\MachineState;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\UnknownRemoteMachineException;
use App\Exception\UnsupportedProviderException;
use App\Message\GetMachine;
use App\MessageHandler\GetMachineHandler;
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
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\ResourceNotFoundException;
use DigitalOceanV2\Exception\RuntimeException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use webignition\ObjectReflector\ObjectReflector;

class GetMachineHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'machine id';
    private const REMOTE_ID = 123;

    private MachineRepository $machineRepository;
    private MachineProviderRepository $machineProviderRepository;
    private DropletApiProxy $dropletApiProxy;
    private MachineNameFactory $machineNameFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $this->machineRepository = $machineRepository;

        $machineProviderStore = self::getContainer()->get(MachineProviderRepository::class);
        \assert($machineProviderStore instanceof MachineProviderRepository);
        $this->machineProviderRepository = $machineProviderStore;

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

    public function testHandlerExistsInContainerAndIsAMessageHandler(): void
    {
        $handler = self::getContainer()->get(GetMachineHandler::class);
        self::assertInstanceOf(GetMachineHandler::class, $handler);
        self::assertCount(1, (new \ReflectionClass($handler::class))->getAttributes(AsMessageHandler::class));
    }

    /**
     * @param DropletEntity[] $getAllOutcome
     *
     * @dataProvider invokeSuccessDataProvider
     */
    public function testInvokeSuccess(
        array $getAllOutcome,
        Machine $machine,
        Machine $expectedMachine,
    ): void {
        $this->dropletApiProxy->withGetAllCall($this->machineNameFactory->create($machine->getId()), $getAllOutcome);

        $this->machineRepository->add($machine);

        $machineProvider = new MachineProvider(self::MACHINE_ID, RemoteMachine::TYPE);
        $this->machineProviderRepository->add($machineProvider);

        $expectedMachineProvider = clone $machineProvider;

        $message = new GetMachine('id0', $machine->getId());

        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher->shouldNotReceive('dispatch');
        $machineRequestDispatcher
            ->shouldReceive('dispatchCollection')
            ->with([])
            ->andReturn([])
        ;

        $handler = $this->createHandler($machineRequestDispatcher);
        ($handler)($message);

        self::assertEquals($expectedMachine, $machine);
        self::assertEquals($expectedMachineProvider, $machineProvider);
    }

    /**
     * @return array<mixed>
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
                'getAllOutcome' => [$createdDropletEntity],
                'machine' => new Machine(self::MACHINE_ID),
                'expectedMachine' => (function () {
                    $machine = new Machine(
                        self::MACHINE_ID,
                        MachineState::UP_STARTED,
                    );
                    $machine->setProvider(MachineProviderEnum::DIGITALOCEAN);

                    return $machine;
                })(),
            ],
            'updated within initial ip addresses' => [
                'getAllOutcome' => [$upNewDropletEntity],
                'machine' => new Machine(self::MACHINE_ID, MachineState::UP_STARTED),
                'expectedMachine' => (function (array $ipAddresses) {
                    $machine = new Machine(
                        self::MACHINE_ID,
                        MachineState::UP_STARTED,
                        $ipAddresses
                    );
                    $machine->setProvider(MachineProviderEnum::DIGITALOCEAN);

                    return $machine;
                })($ipAddresses),
            ],
            'updated within active remote state' => [
                'getAllOutcome' => [$upActiveDropletEntity],
                'machine' => new Machine(
                    self::MACHINE_ID,
                    MachineState::UP_STARTED,
                    $ipAddresses
                ),
                'expectedMachine' => (function (array $ipAddresses) {
                    $machine = new Machine(
                        self::MACHINE_ID,
                        MachineState::UP_ACTIVE,
                        $ipAddresses
                    );
                    $machine->setProvider(MachineProviderEnum::DIGITALOCEAN);

                    return $machine;
                })($ipAddresses),
            ],
        ];
    }

    public function testInvokeUnsupportedProvider(): void
    {
        $machine = new Machine(self::MACHINE_ID, MachineState::FIND_RECEIVED);
        $invalidProvider = 'invalid';
        $machineProvider = new MachineProvider(self::MACHINE_ID, RemoteMachine::TYPE);
        ObjectReflector::setProperty(
            $machineProvider,
            MachineProvider::class,
            'provider',
            $invalidProvider
        );

        $this->machineRepository->add($machine);
        $this->machineProviderRepository->add($machineProvider);

        $message = new GetMachine('id0', $machine->getId());
        $machineState = $machine->getState();

        $unsupportedProviderException = new UnsupportedProviderException($invalidProvider);
        $expectedException = new UnrecoverableMessageHandlingException(
            $unsupportedProviderException->getMessage(),
            $unsupportedProviderException->getCode(),
            $unsupportedProviderException
        );

        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $handler = $this->createHandler($machineRequestDispatcher);

        try {
            ($handler)($message);
            $this->fail($expectedException::class . ' not thrown');
        } catch (\Exception $exception) {
            self::assertEquals($expectedException, $exception);
        }

        self::assertSame($machineState, $machine->getState());
    }

    /**
     * @dataProvider invokeThrowsExceptionDataProvider
     */
    public function testInvokeThrowsException(\Exception $vendorException, \Exception $expectedException): void
    {
        $machine = new Machine(self::MACHINE_ID, MachineState::FIND_RECEIVED);
        $this->machineRepository->add($machine);

        $machineProvider = new MachineProvider(self::MACHINE_ID, RemoteMachine::TYPE);
        $this->machineProviderRepository->add($machineProvider);

        $this->dropletApiProxy->withGetAllCall($this->machineNameFactory->create($machine->getId()), $vendorException);

        $message = new GetMachine('id0', $machine->getId());
        $machineState = $machine->getState();

        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $handler = $this->createHandler($machineRequestDispatcher);

        try {
            ($handler)($message);
            $this->fail($expectedException::class . ' not thrown');
        } catch (\Exception $exception) {
            self::assertEquals($expectedException, $exception);
        }

        self::assertSame($machineState, $machine->getState());
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

        $http404Exception = new ResourceNotFoundException('Not Found', 404);
        $unknownRemoteMachineException = new UnknownRemoteMachineException(
            RemoteMachine::TYPE,
            self::MACHINE_ID,
            MachineAction::GET,
            $http404Exception,
        );

        $http503Exception = new RuntimeException('Service Unavailable', 503);

        return [
            'HTTP 401' => [
                'vendorException' => $http401Exception,
                'expectedException' => new UnrecoverableMessageHandlingException(
                    $authenticationException->getMessage(),
                    $authenticationException->getCode(),
                    $authenticationException
                ),
            ],
            'HTTP 404' => [
                'vendorException' => $http404Exception,
                'expectedException' => new UnrecoverableMessageHandlingException(
                    $unknownRemoteMachineException->getMessage(),
                    $unknownRemoteMachineException->getCode(),
                    $unknownRemoteMachineException
                ),
            ],
            'HTTP 503' => [
                'vendorException' => $http503Exception,
                'expectedException' => new HttpException(
                    self::MACHINE_ID,
                    MachineAction::GET,
                    $http503Exception
                ),
            ],
        ];
    }

    private function createHandler(MachineRequestDispatcher $machineRequestDispatcher): GetMachineHandler
    {
        $machineManager = self::getContainer()->get(MachineManager::class);
        \assert($machineManager instanceof MachineManager);

        $machineUpdater = self::getContainer()->get(MachineUpdater::class);
        \assert($machineUpdater instanceof MachineUpdater);

        return new GetMachineHandler(
            $machineManager,
            $machineRequestDispatcher,
            $machineUpdater,
            $this->machineRepository,
            $this->machineProviderRepository,
        );
    }
}
