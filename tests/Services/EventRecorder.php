<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Event\MachineCreatedEvent;
use App\Event\MachineDeletedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

class EventRecorder implements EventSubscriberInterface, \Countable
{
    /**
     * @var Event[]
     */
    private array $events = [];

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineCreatedEvent::class => [
                ['addEvent', 1000],
            ],
            MachineDeletedEvent::class => [
                ['addEvent', 1000],
            ],
        ];
    }

    public function addEvent(Event $event): void
    {
        $this->events[] = $event;
    }

    public function getLatest(): ?Event
    {
        $latest = $this->events[count($this->events) - 1] ?? null;

        return $latest instanceof Event ? $latest : null;
    }

    public function count(): int
    {
        return count($this->events);
    }

    /**
     * @return Event[]
     */
    public function all(?string $eventName = null): array
    {
        if (null === $eventName) {
            return $this->events;
        }

        $events = [];
        foreach ($this->events as $event) {
            if ($event instanceof $eventName) {
                $events[] = $event;
            }
        }

        return $events;
    }
}
