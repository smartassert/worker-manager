<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\MessageHandlerExceptionFinder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class MessageHandlerExceptionFinderTest extends TestCase
{
    /**
     * @dataProvider findInWorkerMessageFailedEventDataProvider
     */
    public function testFindInWorkerMessageFailedEvent(WorkerMessageFailedEvent $event, \Throwable $expected): void
    {
        $finder = new MessageHandlerExceptionFinder();
        $actual = $finder->findInWorkerMessageFailedEvent($event);

        self::assertSame($expected, $actual);
    }

    /**
     * @return array<mixed>
     */
    public function findInWorkerMessageFailedEventDataProvider(): array
    {
        $runtimeException = new \RuntimeException('runtime exception');

        $emptyUnrecoverableMessageHandlingException = new UnrecoverableMessageHandlingException();
        $unrecoverableMessageHandlingException = new UnrecoverableMessageHandlingException(
            'message',
            0,
            $runtimeException
        );

        return [
            'Event throwable is not HandlerFailedException' => [
                'event' => $this->createEvent($runtimeException),
                'expected' => $runtimeException,
            ],
            'Encapsulated throwable is not UnrecoverableMessageHandlingException' => [
                'event' => $this->createEvent(
                    $this->createHandlerFailedException([
                        $runtimeException,
                    ])
                ),
                'expected' => $runtimeException,
            ],
            'Encapsulated UnrecoverableMessageHandlingException has no previous' => [
                'event' => $this->createEvent(
                    $this->createHandlerFailedException([
                        $emptyUnrecoverableMessageHandlingException,
                    ])
                ),
                'expected' => $emptyUnrecoverableMessageHandlingException,
            ],
            'Encapsulated UnrecoverableMessageHandlingException has previous' => [
                'event' => $this->createEvent(
                    $this->createHandlerFailedException([
                        $unrecoverableMessageHandlingException,
                    ])
                ),
                'expected' => $runtimeException,
            ],
        ];
    }

    private function createEvent(\Throwable $throwable): WorkerMessageFailedEvent
    {
        return new WorkerMessageFailedEvent(new Envelope((object) []), '', $throwable);
    }

    /**
     * @param \Throwable[] $exceptions
     */
    private function createHandlerFailedException(array $exceptions): HandlerFailedException
    {
        return new HandlerFailedException(new Envelope((object) []), $exceptions);
    }
}
