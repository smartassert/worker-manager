<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\MachineManager;

use App\Entity\Machine;
use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Enum\MachineState;
use App\Exception\MachineActionFailedException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\Exception;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\MachineProvider\ProviderMachineNotFoundException;
use App\Exception\Stack;
use App\Model\DigitalOcean\RemoteMachine;
use App\Services\MachineManager\MachineManager;
use App\Services\MachineNameFactory;
use App\Tests\AbstractBaseFunctionalTestCase;
use App\Tests\DataProvider\RemoteRequestThrowsExceptionDataProviderTrait;
use App\Tests\Proxy\DigitalOceanV2\Api\DropletApiProxy;
use App\Tests\Services\EntityRemover;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\ResourceNotFoundException;
use DigitalOceanV2\Exception\RuntimeException;
use DigitalOceanV2\Exception\ValidationFailedException;
use PHPUnit\Framework\Attributes\DataProvider;

class MachineManagerTest extends AbstractBaseFunctionalTestCase
{
    use RemoteRequestThrowsExceptionDataProviderTrait;

    private const MACHINE_ID = 'machine id';

    private MachineManager $machineManager;
    private DropletApiProxy $dropletApiProxy;
    private string $machineName;

    protected function setUp(): void
    {
        parent::setUp();

        $machineManager = self::getContainer()->get(MachineManager::class);
        \assert($machineManager instanceof MachineManager);
        $this->machineManager = $machineManager;

        $dropletApiProxy = self::getContainer()->get(DropletApiProxy::class);
        \assert($dropletApiProxy instanceof DropletApiProxy);
        $this->dropletApiProxy = $dropletApiProxy;

        $machineNameFactory = self::getContainer()->get(MachineNameFactory::class);
        \assert($machineNameFactory instanceof MachineNameFactory);
        $this->machineName = $machineNameFactory->create(self::MACHINE_ID);

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
        }
    }

    public function testCreateSuccess(): void
    {
        $ipAddresses = ['10.0.0.1', '127.0.0.1'];

        $dropletData = [
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
        ];

        $droplet = new DropletEntity($dropletData);
        $this->dropletApiProxy->prepareCreateCall($this->machineName, $droplet);

        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::CREATE_RECEIVED);
        $remoteMachine = $this->machineManager->create($machine);

        self::assertEquals(new RemoteMachine($droplet), $remoteMachine);
    }

    /**
     * @param class-string $expectedExceptionClass
     */
    #[DataProvider('remoteRequestThrowsExceptionDataProvider')]
    public function testCreateThrowsException(\Exception $dropletApiException, string $expectedExceptionClass): void
    {
        try {
            $machine = new Machine(self::MACHINE_ID);
            $machine->setState(MachineState::CREATE_RECEIVED);

            $this->dropletApiProxy->prepareCreateCall($this->machineName, $dropletApiException);
            $this->machineManager->create($machine);

            self::fail(MachineActionFailedException::class . ' not thrown');
        } catch (MachineActionFailedException $exception) {
            $innerException = $exception->getExceptionStack()->first();
            self::assertInstanceOf(ExceptionInterface::class, $innerException);
            self::assertSame($expectedExceptionClass, $innerException::class);
            self::assertSame(MachineAction::CREATE, $innerException->getAction());
            self::assertEquals($dropletApiException, $innerException->getRemoteException());
        }
    }

    public function testCreateThrowsDropletLimitException(): void
    {
        $dropletApiException = new ValidationFailedException(
            'creating this/these droplet(s) will exceed your droplet limit',
            422
        );

        $this->dropletApiProxy->prepareCreateCall($this->machineName, $dropletApiException);

        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::CREATE_RECEIVED);

        try {
            $this->machineManager->create($machine);
            self::fail(ExceptionInterface::class . ' not thrown');
        } catch (MachineActionFailedException $exception) {
            $innerException = $exception->getExceptionStack()->first();
            self::assertInstanceOf(ExceptionInterface::class, $innerException);
            self::assertSame($dropletApiException, $innerException->getRemoteException());
        }
    }

    public function testGetSuccess(): void
    {
        $ipAddresses = ['10.0.0.1', '127.0.0.1'];

        $dropletData = [
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
        ];

        $expectedDropletEntity = new DropletEntity($dropletData);
        $this->dropletApiProxy->withGetAllCall($this->machineName, [$expectedDropletEntity]);

        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::CREATE_RECEIVED);
        $machine->setProvider(MachineProvider::DIGITALOCEAN);

        $remoteMachine = $this->machineManager->get($machine);

        self::assertEquals(new RemoteMachine($expectedDropletEntity), $remoteMachine);
    }

    public function testGetThrowsMachineNotFoundException(): void
    {
        $this->dropletApiProxy->withGetAllCall(
            $this->machineName,
            []
        );

        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::CREATE_RECEIVED);
        $machine->setProvider(MachineProvider::DIGITALOCEAN);

        self::expectExceptionObject(new ProviderMachineNotFoundException(
            $machine->getId(),
            MachineProvider::DIGITALOCEAN->value
        ));

        $this->machineManager->get($machine);
    }

    /**
     * @param class-string $expectedExceptionClass
     */
    #[DataProvider('remoteRequestThrowsExceptionDataProvider')]
    public function testGetThrowsException(\Exception $dropletApiException, string $expectedExceptionClass): void
    {
        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::CREATE_RECEIVED);
        $machine->setProvider(MachineProvider::DIGITALOCEAN);

        try {
            $this->dropletApiProxy->withGetAllCall($this->machineName, $dropletApiException);
            $this->machineManager->get($machine);
            self::fail($dropletApiException::class . ' not thrown');
        } catch (Exception $exception) {
            self::assertSame($expectedExceptionClass, $exception::class);
            self::assertSame(MachineAction::GET, $exception->getAction());
            self::assertEquals($dropletApiException, $exception->getRemoteException());
        }
    }

    #[DataProvider('removeSuccessDataProvider')]
    public function testRemoveSuccess(?\Exception $exception): void
    {
        $this->dropletApiProxy->withRemoveTaggedCall($this->machineName, $exception);

        $this->expectNotToPerformAssertions();

        $this->machineManager->remove(self::MACHINE_ID);
    }

    /**
     * @return array<mixed>
     */
    public static function removeSuccessDataProvider(): array
    {
        return [
            'removed' => [
                'exception' => null,
            ],
            'not found' => [
                'exception' => new ResourceNotFoundException(),
            ],
        ];
    }

    /**
     * @param class-string $expectedExceptionClass
     */
    #[DataProvider('remoteRequestThrowsExceptionDataProvider')]
    public function testRemoveThrowsException(\Exception $dropletApiException, string $expectedExceptionClass): void
    {
        try {
            $this->dropletApiProxy->withRemoveTaggedCall($this->machineName, $dropletApiException);
            $this->machineManager->remove(self::MACHINE_ID);
            self::fail($dropletApiException::class . ' not thrown');
        } catch (MachineActionFailedException $exception) {
            $innerException = $exception->getExceptionStack()->first();
            self::assertInstanceOf(ExceptionInterface::class, $innerException);
            self::assertSame($expectedExceptionClass, $innerException::class);
            self::assertSame(MachineAction::DELETE, $innerException->getAction());
            self::assertEquals($dropletApiException, $innerException->getRemoteException());
        }
    }

    public function testRemoveThrowsMachineNotRemovableException(): void
    {
        $httpException = new RuntimeException('Service Unavailable', 503);

        $this->dropletApiProxy->withRemoveTaggedCall($this->machineName, $httpException);

        $expectedExceptionStack = new Stack([
            new HttpException(self::MACHINE_ID, MachineAction::DELETE, $httpException),
        ]);

        try {
            $this->machineManager->remove(self::MACHINE_ID);
            self::fail(MachineActionFailedException::class . ' not thrown');
        } catch (MachineActionFailedException $machineNotFoundException) {
            self::assertEquals($expectedExceptionStack, $machineNotFoundException->getExceptionStack());
        }
    }

    public function testFindSuccess(): void
    {
        $dropletEntity = new DropletEntity([
            'id' => 123,
            'status' => RemoteMachine::STATE_NEW,
        ]);

        $this->dropletApiProxy->withGetAllCall($this->machineName, [$dropletEntity]);

        $remoteMachine = $this->machineManager->find(self::MACHINE_ID);

        self::assertEquals(new RemoteMachine($dropletEntity), $remoteMachine);
    }

    public function testFindMachineNotFindable(): void
    {
        $http503Exception = new RuntimeException('Service Unavailable', 503);

        $this->dropletApiProxy->withGetAllCall($this->machineName, $http503Exception);

        $expectedExceptionStack = new Stack([
            new HttpException(self::MACHINE_ID, MachineAction::FIND, $http503Exception),
        ]);

        try {
            $this->machineManager->find(self::MACHINE_ID);
            self::fail(MachineActionFailedException::class . ' not thrown');
        } catch (MachineActionFailedException $machineNotFoundException) {
            self::assertEquals($expectedExceptionStack, $machineNotFoundException->getExceptionStack());
        }
    }

    public function testFindMachineDoesNotExist(): void
    {
        $this->dropletApiProxy->withGetAllCall($this->machineName, []);

        self::assertNull($this->machineManager->find(self::MACHINE_ID));
    }
}
