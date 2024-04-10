<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineAction;
use App\Enum\MachineState;
use App\Exception\MachineActionFailedException;
use App\Exception\RecoverableDeciderExceptionInterface;
use App\Exception\UnrecoverableExceptionInterface;
use App\Message\FindMachine;
use App\Model\RemoteMachineInterface;
use App\Repository\MachineRepository;
use App\Services\ExceptionFactory\MachineProvider\ExceptionFactory;
use App\Services\MachineManager\MachineManager;
use App\Services\MachineRequestDispatcher;
use App\Services\MachineUpdater;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class FindMachineHandler
{
    public function __construct(
        private MachineManager $machineManager,
        private MachineUpdater $machineUpdater,
        private MachineRequestDispatcher $machineRequestDispatcher,
        private readonly MachineRepository $machineRepository,
        private readonly ExceptionFactory $exceptionFactory,
    ) {
    }

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

        $machine->setState(MachineState::FIND_FINDING);
        $this->machineRepository->add($machine);

        try {
            $remoteMachine = $this->machineManager->find($machineId);

            if ($remoteMachine instanceof RemoteMachineInterface) {
                $this->machineUpdater->updateFromRemoteMachine($machine, $remoteMachine);

                $onSuccessCollection = $message->getOnSuccessCollection();

                if ($message->getReDispatchOnSuccess()) {
                    $onSuccessCollection[] = $message;
                }

                $this->machineRequestDispatcher->dispatchCollection($onSuccessCollection);
            } else {
                $machine->setState($message->getOnNotFoundState());
                $this->machineRepository->add($machine);

                $this->machineRequestDispatcher->dispatchCollection($message->getOnFailureCollection());
            }
        } catch (MachineActionFailedException $exception) {
            throw new UnrecoverableMessageHandlingException(
                $exception->getMessage(),
                $exception->getCode(),
                $exception
            );
        } catch (\Throwable $exception) {
            $exception = $this->exceptionFactory->create($machineId, MachineAction::FIND, $exception);

            if (
                $exception instanceof UnrecoverableExceptionInterface
                || $exception instanceof RecoverableDeciderExceptionInterface && false === $exception->isRecoverable()
            ) {
                throw new UnrecoverableMessageHandlingException(
                    $exception->getMessage(),
                    $exception->getCode(),
                    $exception
                );
            }

            throw $exception;
        }
    }
}
