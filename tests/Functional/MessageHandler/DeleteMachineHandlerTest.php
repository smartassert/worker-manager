<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineAction;
use App\Enum\MachineState;
use App\Exception\MachineNotRemovableException;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Message\DeleteMachine;
use App\MessageHandler\DeleteMachineHandler;
use App\Repository\MachineRepository;
use App\Services\MachineNameFactory;
use App\Services\MachineRequestDispatcher;
use App\Services\RemoteMachineRemover;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Proxy\DigitalOceanV2\Api\DropletApiProxy;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\TestMachineRequestFactory;
use DigitalOceanV2\Exception\RuntimeException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class DeleteMachineHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

    private Machine $machine;
    private DropletApiProxy $dropletApiProxy;
    private MachineNameFactory $machineNameFactory;
    private TestMachineRequestFactory $machineRequestFactory;

    protected function setUp(): void
    {
        parent::setUp();

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

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $this->machine = new Machine(self::MACHINE_ID, MachineState::DELETE_RECEIVED);
        $machineRepository->add($this->machine);

        $machineRequestFactory = self::getContainer()->get(TestMachineRequestFactory::class);
        \assert($machineRequestFactory instanceof TestMachineRequestFactory);
        $this->machineRequestFactory = $machineRequestFactory;
    }

    public function testHandlerExistsInContainerAndIsAMessageHandler(): void
    {
        $handler = self::getContainer()->get(DeleteMachineHandler::class);
        self::assertInstanceOf(DeleteMachineHandler::class, $handler);
        self::assertCount(1, (new \ReflectionClass($handler::class))->getAttributes(AsMessageHandler::class));
    }

    public function testInvokeSuccess(): void
    {
        self::assertSame(MachineState::DELETE_RECEIVED, $this->machine->getState());

        $expectedMachineName = $this->machineNameFactory->create(self::MACHINE_ID);

        $this->dropletApiProxy->withRemoveTaggedCall($expectedMachineName);

        $message = $this->machineRequestFactory->createDelete(self::MACHINE_ID);
        $expectedMachineRequestCollection = $message->getOnSuccessCollection();

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

        self::assertSame(MachineState::DELETE_REQUESTED, $this->machine->getState());
    }

    public function testInvokeMachineEntityMissing(): void
    {
        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher->shouldNotReceive('dispatch');
        $machineRequestDispatcher->shouldNotReceive('dispatchCollection');

        $handler = $this->createHandler($machineRequestDispatcher);
        ($handler)(new DeleteMachine('id0', 'invalid machine id'));
    }

    /**
     * @dataProvider invokeThrowsExceptionDataProvider
     */
    public function testInvokeThrowsException(\Exception $vendorException, \Exception $expectedException): void
    {
        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher->shouldNotReceive('dispatch');
        $machineRequestDispatcher->shouldNotReceive('dispatchCollection');

        $handler = $this->createHandler($machineRequestDispatcher);

        $expectedMachineName = $this->machineNameFactory->create(self::MACHINE_ID);
        $this->dropletApiProxy->withRemoveTaggedCall($expectedMachineName, $vendorException);

        $message = new DeleteMachine('id0', self::MACHINE_ID);

        try {
            ($handler)($message);
            $this->fail($expectedException::class . ' not thrown');
        } catch (\Exception $exception) {
            self::assertEquals($expectedException, $exception);
        }

        self::assertSame(MachineState::DELETE_REQUESTED, $this->machine->getState());
    }

    /**
     * @return array<mixed>
     */
    public function invokeThrowsExceptionDataProvider(): array
    {
        $http401Exception = new RuntimeException('Unauthorized', 401);

        $authenticationException = new AuthenticationException(
            self::MACHINE_ID,
            MachineAction::DELETE,
            $http401Exception
        );

        $http503Exception = new RuntimeException('Service Unavailable', 503);

        $serviceUnavailableException = new HttpException(
            self::MACHINE_ID,
            MachineAction::DELETE,
            $http503Exception
        );

        $machineNotRemovableAuthenticationException = new MachineNotRemovableException(self::MACHINE_ID, [
            $authenticationException,
        ]);

        $machineNotRemovableServiceUnavailableException = new MachineNotRemovableException(self::MACHINE_ID, [
            $serviceUnavailableException,
        ]);

        return [
            'HTTP 401' => [
                'vendorException' => $http401Exception,
                'expectedException' => new UnrecoverableMessageHandlingException(
                    $machineNotRemovableAuthenticationException->getMessage(),
                    $machineNotRemovableAuthenticationException->getCode(),
                    $machineNotRemovableAuthenticationException
                ),
            ],
            'HTTP 503' => [
                'vendorException' => $http503Exception,
                'expectedException' => new UnrecoverableMessageHandlingException(
                    $machineNotRemovableServiceUnavailableException->getMessage(),
                    $machineNotRemovableServiceUnavailableException->getCode(),
                    $machineNotRemovableServiceUnavailableException
                ),
            ],
        ];
    }

    private function createHandler(MachineRequestDispatcher $machineRequestDispatcher): DeleteMachineHandler
    {
        $remoteMachineRemover = self::getContainer()->get(RemoteMachineRemover::class);
        \assert($remoteMachineRemover instanceof RemoteMachineRemover);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        return new DeleteMachineHandler($remoteMachineRemover, $machineRequestDispatcher, $machineRepository);
    }
}
