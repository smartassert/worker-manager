<?php

declare(strict_types=1);

namespace App\EvenDispatcher;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Event\MachineStateChangedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class MachineStateChangedEventDispatcher
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function dispatch(Machine $machine, ?MachineState $newState): void
    {
        if (null !== $newState && $machine->getState() !== $newState) {
            $this->eventDispatcher->dispatch(new MachineStateChangedEvent($machine, $newState));
        }
    }
}
