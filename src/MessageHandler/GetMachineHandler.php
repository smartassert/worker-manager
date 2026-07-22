<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Enum\MessageHandlingReadiness;
use App\Exception\UnrecoverableExceptionInterface;
use App\Message\GetMachine;
use App\ReadinessAssessor\GetMachineReadinessAssessor;
use App\Repository\MachineRepository;
use App\Services\MachineManager\MachineManager;
use App\Services\MachineUpdater;
use App\Services\UnhandleableMessageHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class GetMachineHandler
{
    public function __construct(
        private readonly GetMachineReadinessAssessor $readinessAssessor,
        private readonly UnhandleableMessageHandler $unhandleableMessageHandler,
        private MachineManager $machineManager,
        private MessageBusInterface $messageBus,
        private MachineUpdater $machineUpdater,
        private readonly MachineRepository $machineRepository,
    ) {}

    /**
     * @throws \Throwable
     */
    public function __invoke(GetMachine $message): void
    {
        $readiness = $this->readinessAssessor->isReady($message->getMachineId());
        if (MessageHandlingReadiness::NOW !== $readiness) {
            $this->unhandleableMessageHandler->handle($message, $readiness);
        }

        $machine = $this->machineRepository->find($message->getMachineId());
        if (!$machine instanceof Machine) {
            return;
        }

        try {
            $remoteMachine = $this->machineManager->get($machine);
            $this->machineUpdater->updateFromRemoteMachine($machine, $remoteMachine);

            foreach ($message->getOnSuccessCollection() as $onSuccessRequest) {
                $this->messageBus->dispatch($onSuccessRequest);
            }
        } catch (UnrecoverableExceptionInterface $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
