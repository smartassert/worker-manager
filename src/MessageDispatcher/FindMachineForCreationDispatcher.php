<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Entity\Machine;
use App\Enum\MessageHandlingReadiness;
use App\ReadinessAssessor\CreateMachineReadinessAssessor;
use App\Services\MachineRequestFactory;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class FindMachineForCreationDispatcher implements FindMachineForCreationDispatcherInterface
{
    public function __construct(
        private CreateMachineReadinessAssessor $readinessAssessor,
        private MachineRequestFactory $machineRequestFactory,
        private MessageBusInterface $messageBus,
    ) {}

    /**
     * @throws ExceptionInterface
     */
    public function dispatchForMachine(Machine $machine): void
    {
        $readiness = $this->readinessAssessor->isReady($machine->getId());
        if (MessageHandlingReadiness::NEVER === $readiness) {
            return;
        }

        $message = $this
            ->machineRequestFactory
            ->createFindMachineForCreation($machine->getId())
        ;

        $this->messageBus->dispatch($message);
    }
}
