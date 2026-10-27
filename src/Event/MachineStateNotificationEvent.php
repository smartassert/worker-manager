<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Machine;
use App\Event\MachineEventInterface as MachineEvent;
use App\Event\NotifiableEventInterface as NotifiableEvent;
use App\Model\Machine as MachineModel;
use App\Model\SerializableMachineInterface;

/**
 * @phpstan-import-type SerializedMachine from SerializableMachineInterface
 */
class MachineStateNotificationEvent extends AbstractMachineEvent implements MachineEvent, NotifiableEvent
{
    public const string REMOTE_EVENT_NAME = 'worker-manager.machine.state_changed';

    public function __construct(
        Machine $machine,
        private readonly MachineModel $machineModel,
        private readonly string $notifyUrl,
    ) {
        parent::__construct($machine);
    }

    public function getNotifyUrl(): string
    {
        return rtrim($this->notifyUrl, '/') . '/' . $this->getRemoteEventName();
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
        return $this->machineModel->toArray();
    }
}
