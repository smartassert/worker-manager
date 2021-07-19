<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Message\DeleteMachine;
use App\Message\FindMachine;
use App\MessageHandler\DeleteMachineHandler;
use App\Model\MachineActionInterface;
use App\Services\Entity\Store\MachineStore;
use App\Services\ExceptionLogger;
use App\Services\MachineRequestFactory;
use App\Services\RequestIdFactoryInterface;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\Services\MockExceptionLogger;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\SequentialRequestIdFactory;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use webignition\ObjectReflector\ObjectReflector;

class DeleteMachineHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

    private DeleteMachineHandler $handler;
    private MessengerAsserter $messengerAsserter;
    private MockHandler $mockHandler;
    private Machine $machine;
    private MachineRequestFactory $machineRequestFactory;
    private SequentialRequestIdFactory $requestIdFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::$container->get(DeleteMachineHandler::class);
        \assert($handler instanceof DeleteMachineHandler);
        $this->handler = $handler;

        $machineStore = self::$container->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $this->machine = new Machine(self::MACHINE_ID);
        $machineStore->store($this->machine);

        $machineStore = self::$container->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $this->machine->setState(Machine::STATE_DELETE_RECEIVED);
        $machineStore->store($this->machine);

        $messengerAsserter = self::$container->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $mockHandler = self::$container->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $machineRequestFactory = self::$container->get(MachineRequestFactory::class);
        \assert($machineRequestFactory instanceof MachineRequestFactory);
        $this->machineRequestFactory = $machineRequestFactory;

        $requestIdFactory = self::$container->get(RequestIdFactoryInterface::class);
        \assert($requestIdFactory instanceof SequentialRequestIdFactory);
        $this->requestIdFactory = $requestIdFactory;
    }

    public function testInvokeSuccess(): void
    {
        $this->messengerAsserter->assertQueueIsEmpty();
        self::assertSame(Machine::STATE_DELETE_RECEIVED, $this->machine->getState());

        $this->setExceptionLoggerOnHandler(
            (new MockExceptionLogger())
                ->withoutLogCall()
                ->getMock()
        );

        $this->mockHandler->append(new Response(204));

        $message = $this->machineRequestFactory->createDelete(self::MACHINE_ID);

        ($this->handler)($message);

        $this->requestIdFactory->reset();

        self::assertSame(Machine::STATE_DELETE_REQUESTED, $this->machine->getState());

        $expectedMessage = $this->machineRequestFactory
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

        $this->setExceptionLoggerOnHandler(
            (new MockExceptionLogger())
                ->withoutLogCall()
                ->getMock()
        );

        ($this->handler)(new DeleteMachine('id0', $machineId));

        $this->messengerAsserter->assertQueueIsEmpty();
    }

    public function testInvokeRemoteMachineNotRemovable(): void
    {
        $this->messengerAsserter->assertQueueIsEmpty();

        $this->setExceptionLoggerOnHandler(
            (new MockExceptionLogger())
                ->withLogCall(new HttpException(
                    self::MACHINE_ID,
                    MachineActionInterface::ACTION_DELETE,
                    new RuntimeException('Service Unavailable', 503)
                ))
                ->getMock()
        );

        $this->mockHandler->append(new Response(503));

        $message = new DeleteMachine('id0', self::MACHINE_ID);
        ObjectReflector::setProperty($message, DeleteMachine::class, 'retryCount', 11);

        ($this->handler)($message);

        $this->messengerAsserter->assertQueueIsEmpty();

        self::assertSame(Machine::STATE_DELETE_FAILED, $this->machine->getState());
        $this->messengerAsserter->assertQueueIsEmpty();
    }

    private function setExceptionLoggerOnHandler(ExceptionLogger $exceptionLogger): void
    {
        ObjectReflector::setProperty(
            $this->handler,
            DeleteMachineHandler::class,
            'exceptionLogger',
            $exceptionLogger
        );
    }
}
