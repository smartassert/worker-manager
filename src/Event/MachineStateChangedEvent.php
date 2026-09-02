<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Model\Machine as MachineModel;
use App\Model\SerializableMachineInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @phpstan-import-type SerializedMachine from SerializableMachineInterface
 */
class MachineStateChangedEvent extends Event implements MachineEventInterface, NotifiableEventInterface
{
    public const string REMOTE_EVENT_NAME = 'worker-manager.machine.state_changed';

    public function __construct(
        private readonly Machine $machine,
        private readonly MachineState $newState,
    ) {}

    public function getMachine(): Machine
    {
        return $this->machine;
    }

    public function getNewState(): MachineState
    {
        return $this->newState;
    }

    public function getNotifyUrl(): ?string
    {
        return $this->machine->getNotifyUrl();
    }

    public function getRemoteEventName(): string
    {
        return self::REMOTE_EVENT_NAME;
    }

    /**
     * @return SerializedMachine
     */
    public function getPayload(): array
    {
        return new MachineModel($this->machine)->toArray();
    }
}
