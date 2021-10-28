<?php

namespace App\Services\ServiceStatusInspector;

use App\Message\CheckMachineIsActive;
use App\Message\UniqueId;
use Symfony\Component\Messenger\MessageBusInterface;

class MessageQueueInspector
{
    public const INVALID_MACHINE_ID = 'intentionally invalid';

    public function __construct(
        private MessageBusInterface $messageBus
    ) {
    }

    public function __invoke(): void
    {
        $this->messageBus->dispatch(new CheckMachineIsActive(
            UniqueId::create(),
            self::INVALID_MACHINE_ID,
        ));
    }
}
