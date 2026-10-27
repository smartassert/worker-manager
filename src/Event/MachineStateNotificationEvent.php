<?php

declare(strict_types=1);

namespace App\Event;

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

    /**
     * @param non-empty-string $machineId
     */
    public function __construct(
        string $machineId,
        private readonly MachineModel $machineModel,
        private readonly string $notifyUrl,
    ) {
        parent::__construct($machineId);
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
