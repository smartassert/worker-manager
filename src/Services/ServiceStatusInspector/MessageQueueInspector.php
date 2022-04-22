<?php

declare(strict_types=1);

namespace App\Services\ServiceStatusInspector;

use App\Message\CheckMachineIsActive;
use App\Message\UniqueId;
use SmartAssert\ServiceStatusInspector\ComponentStatusInspectorInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MessageQueueInspector implements ComponentStatusInspectorInterface
{
    public const INVALID_MACHINE_ID = 'intentionally invalid';
    public const DEFAULT_IDENTIFIER = 'message_queue';

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly string $identifier = self::DEFAULT_IDENTIFIER,
    ) {
    }

    public function getStatus(): bool
    {
        $this->messageBus->dispatch(new CheckMachineIsActive(UniqueId::create(), self::INVALID_MACHINE_ID));

        return true;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
