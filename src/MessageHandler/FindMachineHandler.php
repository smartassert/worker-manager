<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Exception\UnrecoverableExceptionInterface;
use App\Message\FindMachine;
use App\Model\RemoteMachineInterface;
use App\Repository\MachineRepository;
use App\Services\MachineManager\MachineManager;
use App\Services\MachineMutator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class FindMachineHandler
{
    public function __construct(
        private MachineManager $machineManager,
        private MachineMutator $machineMutator,
        private MessageBusInterface $messageBus,
        private readonly MachineRepository $machineRepository,
    ) {}

    /**
     * @throws \Throwable
     */
    public function __invoke(FindMachine $message): void
    {
        $machineId = $message->getMachineId();

        $machine = $this->machineRepository->find($machineId);
        if (!$machine instanceof Machine) {
            return;
        }

        $this->machineMutator->setState($machine, MachineState::FIND_FINDING);

        try {
            $remoteMachine = $this->machineManager->find($machineId);

            if ($remoteMachine instanceof RemoteMachineInterface) {
                $this->machineMutator->updateFromRemoteMachine($machine, $remoteMachine);

                $onSuccessCollection = $message->getOnSuccessCollection();

                if ($message->getReDispatchOnSuccess()) {
                    $onSuccessCollection[] = $message;
                }

                foreach ($onSuccessCollection as $onSuccessRequest) {
                    $this->messageBus->dispatch($onSuccessRequest);
                }
            } else {
                $this->machineMutator->setState($machine, $message->getOnNotFoundState());

                foreach ($message->getOnFailureCollection() as $onFailureRequest) {
                    $this->messageBus->dispatch($onFailureRequest);
                }
            }
        } catch (UnrecoverableExceptionInterface $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
