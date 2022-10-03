<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\Messenger;

use App\EventListener\Messenger\MachineRequestFailureHandler;
use App\Message\GetMachine;
use App\Repository\MachineRepository;
use App\Services\Entity\Factory\CreateFailureFactory;
use App\Services\MessageHandlerExceptionFinder;
use App\Services\MessageHandlerExceptionStackFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\Repository\MockMachineRepository;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Log\LoggerInterface;
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

        $handler = $this->createHandler(\Mockery::mock(MachineRepository::class));
        $handler->onMessageFailed($event);

        self::expectNotToPerformAssertions();
    }

    public function testOnMessageFailedForNonMachineRequestInterfaceMessage(): void
    {
        $event = $this->createEvent();

        $handler = $this->createHandler(\Mockery::mock(MachineRepository::class));
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
            (new MockMachineRepository())
                ->withFindCall(self::MACHINE_ID, null)
                ->getMock()
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
        MachineRepository $machineRepository,
        ?LoggerInterface $logger = null
    ): MachineRequestFailureHandler {
        return new MachineRequestFailureHandler(
            \Mockery::mock(CreateFailureFactory::class),
            \Mockery::mock(MessageHandlerExceptionFinder::class),
            \Mockery::mock(MessageHandlerExceptionStackFactory::class),
            $logger instanceof LoggerInterface ? $logger : \Mockery::mock(LoggerInterface::class),
            $machineRepository,
        );
    }
}
