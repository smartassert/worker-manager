<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\MessageHandlingReadiness;
use App\Event\MessageNotHandleableEvent;
use App\Message\MachineActionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

readonly class UnhandleableMessageHandler
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {}

    public function handle(MachineActionInterface $message, MessageHandlingReadiness $readiness): void
    {
        $this->logger->info(
            sprintf(
                'Failed to %s machine "%s": %s handleable',
                $message->getAction()->value,
                $message->getMachineId(),
                MessageHandlingReadiness::NEVER === $readiness ? 'never' : 'not yet'
            )
        );

        $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($message, $readiness));
    }
}
