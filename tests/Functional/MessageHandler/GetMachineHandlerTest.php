<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\UnknownRemoteMachineException;
use App\Exception\UnsupportedProviderException;
use App\Message\GetMachine;
use App\MessageHandler\GetMachineHandler;
use App\Model\DigitalOcean\RemoteMachine;
use App\Model\MachineActionInterface;
use App\Model\ProviderInterface;
use App\Services\Entity\Store\MachineProviderStore;
use App\Services\Entity\Store\MachineStore;
use App\Services\MachineNameFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Proxy\DigitalOceanV2\Api\DropletApiProxy;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\ResourceNotFoundException;
use DigitalOceanV2\Exception\RuntimeException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use webignition\ObjectReflector\ObjectReflector;

class GetMachineHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'machine id';
    private const REMOTE_ID = 123;

    private GetMachineHandler $handler;
    private MessengerAsserter $messengerAsserter;
    private MachineStore $machineStore;
    private MachineProviderStore $machineProviderStore;
    private DropletApiProxy $dropletApiProxy;
    private MachineNameFactory $machineNameFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(GetMachineHandler::class);
        \assert($handler instanceof GetMachineHandler);
        $this->handler = $handler;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $machineStore = self::getContainer()->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $this->machineStore = $machineStore;

        $machineProviderStore = self::getContainer()->get(MachineProviderStore::class);
        \assert($machineProviderStore instanceof MachineProviderStore);
        $this->machineProviderStore = $machineProviderStore;

        $dropletApiProxy = self::getContainer()->get(DropletApiProxy::class);
        \assert($dropletApiProxy instanceof DropletApiProxy);
        $this->dropletApiProxy = $dropletApiProxy;

        $machineNameFactory = self::getContainer()->get(MachineNameFactory::class);
        \assert($machineNameFactory instanceof MachineNameFactory);
        $this->machineNameFactory = $machineNameFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
        }
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

        $this->machineStore->store($machine);

        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        $this->machineProviderStore->store($machineProvider);

        $expectedMachineProvider = clone $machineProvider;

        $message = new GetMachine('id0', $machine->getId());
        ($this->handler)($message);

        self::assertEquals($expectedMachine, $machine);
        self::assertEquals($expectedMachineProvider, $machineProvider);

        $this->messengerAsserter->assertQueueIsEmpty();
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
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    Machine::STATE_UP_STARTED
                ),
            ],
            'updated within initial ip addresses' => [
                'getAllOutcome' => [$upNewDropletEntity],
                'machine' => new Machine(self::MACHINE_ID, Machine::STATE_UP_STARTED),
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    Machine::STATE_UP_STARTED,
                    $ipAddresses
                ),
            ],
            'updated within active remote state' => [
                'getAllOutcome' => [$upActiveDropletEntity],
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

    public function testInvokeUnsupportedProvider(): void
    {
        $machine = new Machine(self::MACHINE_ID, Machine::STATE_FIND_RECEIVED);
        $invalidProvider = 'invalid';
        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        ObjectReflector::setProperty(
            $machineProvider,
            MachineProvider::class,
            'provider',
            $invalidProvider
        );

        $this->machineStore->store($machine);
        $this->machineProviderStore->store($machineProvider);

        $message = new GetMachine('id0', $machine->getId());
        $machineState = $machine->getState();

        $unsupportedProviderException = new UnsupportedProviderException($invalidProvider);
        $expectedException = new UnrecoverableMessageHandlingException(
            $unsupportedProviderException->getMessage(),
            $unsupportedProviderException->getCode(),
            $unsupportedProviderException
        );

        try {
            ($this->handler)($message);
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
        $machine = new Machine(self::MACHINE_ID, Machine::STATE_FIND_RECEIVED);
        $this->machineStore->store($machine);

        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        $this->machineProviderStore->store($machineProvider);

        $this->dropletApiProxy->withGetAllCall($this->machineNameFactory->create($machine->getId()), $vendorException);

        $message = new GetMachine('id0', $machine->getId());
        $machineState = $machine->getState();

        try {
            ($this->handler)($message);
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
            MachineActionInterface::ACTION_GET,
            $http401Exception
        );

        $http404Exception = new ResourceNotFoundException('Not Found', 404);
        $unknownRemoteMachineException = new UnknownRemoteMachineException(
            ProviderInterface::NAME_DIGITALOCEAN,
            self::MACHINE_ID,
            MachineActionInterface::ACTION_GET,
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
                    MachineActionInterface::ACTION_GET,
                    $http503Exception
                ),
            ],
        ];
    }
}
