<?php

namespace App\Controller;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\MessageDispatcher\FindMachineForCreationDispatcherInterface;
use App\Model\Machine as MachineModel;
use App\Repository\ActionFailureRepository;
use App\Repository\MachineRepository;
use App\Response\BadMachineCreateRequestResponse;
use App\Services\MachineMutator;
use App\Services\MachineRequestFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

readonly class MachineController
{
    public const PATH_COMPONENT_ID = '{id}';
    public const PATH_MACHINE = '/machine/' . self::PATH_COMPONENT_ID;

    public function __construct(
        private MessageBusInterface $messageBus,
        private MachineRequestFactory $machineRequestFactory,
        private MachineRepository $machineRepository,
        private MachineMutator $machineMutator,
    ) {}

    /**
     * @param non-empty-string $id
     *
     * @throws ExceptionInterface
     */
    #[Route(self::PATH_MACHINE, name: 'machine-create', methods: ['POST'])]
    public function create(
        string $id,
        FindMachineForCreationDispatcherInterface $messageDispatcher,
    ): JsonResponse {
        $machine = $this->machineRepository->find($id);
        if ($machine instanceof Machine) {
            if (!$machine->getState()->isResettable()) {
                return BadMachineCreateRequestResponse::createIdTakenResponse();
            }
        } else {
            $machine = new Machine($id);
        }

        $this->machineMutator->setState($machine, MachineState::CREATE_RECEIVED);
        $messageDispatcher->dispatchForMachine($machine);

        return new JsonResponse(new MachineModel($machine), 202);
    }

    /**
     * @param non-empty-string $id
     *
     * @throws ExceptionInterface
     */
    #[Route(self::PATH_MACHINE, name: 'machine-status', methods: ['GET', 'HEAD'])]
    public function status(string $id, ActionFailureRepository $actionFailureRepository): JsonResponse
    {
        $machine = $this->machineRepository->find($id);
        if (!$machine instanceof Machine) {
            $machine = new Machine($id);
            $this->machineMutator->setState($machine, MachineState::FIND_RECEIVED);

            $this->messageBus->dispatch(
                $this->machineRequestFactory->createFindThenGet($id)
            );
        }

        return new JsonResponse(new MachineModel($machine, $actionFailureRepository->find($id)));
    }

    /**
     * @param non-empty-string $id
     *
     * @throws ExceptionInterface
     */
    #[Route(self::PATH_MACHINE, name: 'machine-delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $machine = $this->machineRepository->find($id);
        if (!$machine instanceof Machine) {
            $machine = new Machine($id);
        }

        $this->machineMutator->setState($machine, MachineState::DELETE_RECEIVED);

        $this->messageBus->dispatch(
            $this->machineRequestFactory->createDelete($id)
        );

        return new JsonResponse(new MachineModel($machine), 202);
    }
}
