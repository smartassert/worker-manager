<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Exception\MachineNotRemovableException;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Message\DeleteMachine;
use App\Message\FindMachine;
use App\MessageHandler\DeleteMachineHandler;
use App\Model\MachineActionInterface;
use App\Repository\MachineRepository;
use App\Services\MachineNameFactory;
use App\Services\MachineRequestFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Proxy\DigitalOceanV2\Api\DropletApiProxy;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\SequentialRequestIdFactory;
use App\Tests\Services\TestMachineRequestFactory;
use DigitalOceanV2\Exception\RuntimeException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class DeleteMachineHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

    private DeleteMachineHandler $handler;
    private MessengerAsserter $messengerAsserter;
    private Machine $machine;
    private DropletApiProxy $dropletApiProxy;
    private MachineNameFactory $machineNameFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(DeleteMachineHandler::class);
        \assert($handler instanceof DeleteMachineHandler);
        $this->handler = $handler;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

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
        $this->machine = new Machine(self::MACHINE_ID, Machine::STATE_DELETE_RECEIVED);
        $machineRepository->add($this->machine);
    }

    public function testHandlerExistsInContainerAndIsAMessageHandler(): void
    {
        $handler = self::getContainer()->get(DeleteMachineHandler::class);
        self::assertInstanceOf(DeleteMachineHandler::class, $handler);
        self::assertInstanceOf(MessageHandlerInterface::class, $handler);
    }

    public function testInvokeSuccess(): void
    {
        $this->messengerAsserter->assertQueueIsEmpty();
        self::assertSame(Machine::STATE_DELETE_RECEIVED, $this->machine->getState());

        $expectedMachineName = $this->machineNameFactory->create(self::MACHINE_ID);

        $this->dropletApiProxy->withRemoveTaggedCall($expectedMachineName);

        $requestIdFactory = new SequentialRequestIdFactory();
        $machineRequestFactory = new TestMachineRequestFactory(
            new MachineRequestFactory($requestIdFactory)
        );

        $message = $machineRequestFactory->createDelete(self::MACHINE_ID);

        ($this->handler)($message);

        $requestIdFactory->reset();

        self::assertSame(Machine::STATE_DELETE_REQUESTED, $this->machine->getState());

        $expectedMessage = $machineRequestFactory
            ->createFind(self::MACHINE_ID)
            ->withOnNotFoundState(Machine::STATE_DELETE_DELETED)
            ->withReDispatchOnSuccess(true)
        ;

        self::assertInstanceOf(FindMachine::class, $expectedMessage);

        $this->messengerAsserter->assertMessageAtPositionEquals(0, $expectedMessage);
    }

    public function testInvokeMachineEntityMissing(): void
    {
        $this->messengerAsserter->assertQueueIsEmpty();
        $machineId = 'invalid machine id';

        ($this->handler)(new DeleteMachine('id0', $machineId));

        $this->messengerAsserter->assertQueueIsEmpty();
    }

    /**
     * @dataProvider invokeThrowsExceptionDataProvider
     */
    public function testInvokeThrowsException(\Exception $vendorException, \Exception $expectedException): void
    {
        $expectedMachineName = $this->machineNameFactory->create(self::MACHINE_ID);
        $this->dropletApiProxy->withRemoveTaggedCall($expectedMachineName, $vendorException);

        $message = new DeleteMachine('id0', self::MACHINE_ID);

        try {
            ($this->handler)($message);
            $this->fail($expectedException::class . ' not thrown');
        } catch (\Exception $exception) {
            self::assertEquals($expectedException, $exception);
        }

        self::assertSame(Machine::STATE_DELETE_REQUESTED, $this->machine->getState());
    }

    /**
     * @return array<mixed>
     */
    public function invokeThrowsExceptionDataProvider(): array
    {
        $http401Exception = new RuntimeException('Unauthorized', 401);

        $authenticationException = new AuthenticationException(
            self::MACHINE_ID,
            MachineActionInterface::ACTION_DELETE,
            $http401Exception
        );

        $http503Exception = new RuntimeException('Service Unavailable', 503);

        $serviceUnavailableException = new HttpException(
            self::MACHINE_ID,
            MachineActionInterface::ACTION_DELETE,
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
}
