<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\Messenger;

use App\EventListener\Messenger\MachineRequestFailureHandler;
use App\Message\GetMachine;
use App\Repository\MachineRepository;
use App\Services\Entity\Factory\CreateFailureFactory;
use App\Services\MessageHandlerExceptionFinder;
use App\Services\MessageHandlerExceptionStackFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class MachineRequestFailureHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'machine-id';

    public function testOnMessageFailedForRetryableEvent(): void
    {
        $event = $this->createEvent();
        $event->setForRetry();

        $machineRepository = \Mockery::mock(MachineRepository::class);
        $machineRepository
            ->shouldNotReceive('find')
        ;

        $handler = $this->createHandler($machineRepository);
        $handler->onMessageFailed($event);
    }

    public function testOnMessageFailedForNonMachineRequestInterfaceMessage(): void
    {
        $event = $this->createEvent();

        $machineRepository = \Mockery::mock(MachineRepository::class);
        $machineRepository
            ->shouldNotReceive('find')
        ;

        $handler = $this->createHandler($machineRepository);
        $handler->onMessageFailed($event);
    }

    public function testOnMessageFailedMachineDoesNotExist(): void
    {
        $event = $this->createEvent(new GetMachine(
            'unique id',
            self::MACHINE_ID
        ));

        $machineRepository = \Mockery::mock(MachineRepository::class);
        $machineRepository
            ->shouldReceive('find')
            ->withArgs(function (string $machineId) {
                self::assertSame(self::MACHINE_ID, $machineId);

                return true;
            })
            ->andReturn(null)
        ;

        $handler = $this->createHandler($machineRepository);

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
