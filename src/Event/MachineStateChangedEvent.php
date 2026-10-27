<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Model\Machine as MachineModel;
use App\Model\SerializableMachineInterface;

/**
 * @phpstan-import-type SerializedMachine from SerializableMachineInterface
 */
class MachineStateChangedEvent extends AbstractMachineEvent implements MachineEventInterface, NotifiableEventInterface
{
    public const string REMOTE_EVENT_NAME = 'worker-manager.machine.state_changed';

    public function __construct(
        Machine $machine,
        private readonly MachineState $newState,
    ) {
        parent::__construct($machine);
    }

    public function getNewState(): MachineState
    {
        return $this->newState;
    }

    public function getNotifyUrl(): ?string
    {
        $baseUrl = $this->getMachine()->getNotifyUrl();
        if (null === $baseUrl) {
            return null;
        }

        return rtrim($baseUrl, '/') . '/' . $this->getRemoteEventName();
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
        return new MachineModel($this->getMachine())->toArray();
    }
}
