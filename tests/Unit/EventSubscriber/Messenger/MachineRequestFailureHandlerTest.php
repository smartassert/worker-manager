<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber\Messenger;

use App\Entity\Machine;
use App\EventListener\Messenger\MachineRequestFailureHandler;
use App\Exception\MachineNotFindableException;
use App\Message\GetMachine;
use App\Services\Entity\Factory\CreateFailureFactory;
use App\Services\Entity\Store\MachineStore;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\Services\MockExceptionLogger;
use App\Tests\Mock\Services\MockMachineStore;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use SmartAssert\InvokableLogger\ExceptionLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class MachineRequestFailureHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'machine-id';

    public function testOnMessageFailedForRetryableEvent(): void
    {
        $event = $this->createEvent();
        $event->setForRetry();

        $handler = $this->createHandler(\Mockery::mock(MachineStore::class));
        $handler->onMessageFailed($event);

        self::expectNotToPerformAssertions();
    }

    public function testOnMessageFailedForNonMachineRequestInterfaceMessage(): void
    {
        $event = $this->createEvent();

        $handler = $this->createHandler(\Mockery::mock(MachineStore::class));
        $handler->onMessageFailed($event);

        self::expectNotToPerformAssertions();
    }

    public function testOnMessageFailedMachineDoesNotExist(): void
    {
        $event = $this->createEvent(new GetMachine(
            'unique id',
            self::MACHINE_ID
        ));

        $handler = $this->createHandler(
            (new MockMachineStore())
                ->withFindCall(self::MACHINE_ID, null)
                ->getMock()
        );

        $handler->onMessageFailed($event);
    }

    public function testOnMessageFailedExceptionLoggingForStackableException(): void
    {
        $exception1 = new \Exception();
        $exception2 = new \Exception();

        $exceptionStack = [
            $exception1,
            $exception2,
        ];

        $exception = \Mockery::mock(MachineNotFindableException::class);
        $exception
            ->shouldReceive('getExceptionStack')
            ->andReturn($exceptionStack)
        ;

        $exceptionLogger = (new MockExceptionLogger())
            ->withLogCalls($exceptionStack)
            ->getMock()
        ;

        $this->doTestOnMessageFailedExceptionLogging($exception, $exceptionLogger);
    }

    public function testOnMessageFailedExceptionLoggingForNonStackableException(): void
    {
        $exception = new \Exception();

        $exceptionLogger = (new MockExceptionLogger())
            ->withLogCalls([$exception])
            ->getMock()
        ;

        $this->doTestOnMessageFailedExceptionLogging($exception, $exceptionLogger);
    }

    private function doTestOnMessageFailedExceptionLogging(
        \Throwable $exception,
        ExceptionLogger $exceptionLogger
    ): void {
        $event = $this->createEvent(
            new GetMachine(
                'unique id',
                self::MACHINE_ID
            ),
            $exception
        );

        $machine = new Machine(self::MACHINE_ID);

        $handler = $this->createHandler(
            (new MockMachineStore())
                ->withFindCall(self::MACHINE_ID, $machine)
                ->withStoreCall($machine)
                ->getMock(),
            $exceptionLogger
        );

        $handler->onMessageFailed($event);
    }

    private function createEvent(?object $message = null, ?\Throwable $throwable = null): WorkerMessageFailedEvent
    {
        $throwable = $throwable instanceof \Throwable ? $throwable : new \Exception();

        $message = is_object($message) ? $message : (object) [];

        return new WorkerMessageFailedEvent(new Envelope($message), 'not relevant', $throwable);
    }

    private function createHandler(
        MachineStore $machineStore,
        ?ExceptionLogger $exceptionLogger = null
    ): MachineRequestFailureHandler {
        $exceptionLogger = $exceptionLogger instanceof ExceptionLogger
            ? $exceptionLogger
            : \Mockery::mock(ExceptionLogger::class);

        return new MachineRequestFailureHandler(
            $machineStore,
            \Mockery::mock(CreateFailureFactory::class),
            $exceptionLogger
        );
    }
}
