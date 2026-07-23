<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\CreateMachineEvent;
use App\ReadinessAssessor\CreateMachineReadinessAssessor;
use App\Services\MachineRequestFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class CreateMachineDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private CreateMachineReadinessAssessor $readinessAssessor,
        private MachineRequestFactory $machineRequestFactory,
        private MessageBusInterface $messageBus,
    ) {}

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CreateMachineEvent::class => [
                ['dispatchForCreateMachineEvent', 100],
            ],
        ];
    }

    /**
     * @throws ExceptionInterface
     */
    public function dispatchForCreateMachineEvent(CreateMachineEvent $event): void
    {
        $readiness = $this->readinessAssessor->isReady($event->getMachine()->getId());
        if (MessageHandlingReadiness::NEVER === $readiness) {
            return;
        }

        $message = $this
            ->machineRequestFactory
            ->createCreate($event->getMachine()->getId())
        ;

        $this->messageBus->dispatch($message);
    }
}
