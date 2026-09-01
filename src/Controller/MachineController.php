<?php

namespace App\Controller;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\EvenDispatcher\MachineStateChangedEventDispatcher;
use App\MessageDispatcher\DeleteMachineDispatcherInterface;
use App\MessageDispatcher\FindMachineForCreationDispatcherInterface;
use App\MessageDispatcher\FindMachineForRetrievalDispatcherInterface;
use App\Model\Machine as MachineModel;
use App\Repository\ActionFailureRepository;
use App\Repository\MachineRepository;
use App\Request\CreateMachineRequest;
use App\Response\BadMachineCreateRequestResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/machine/{id}')]
readonly class MachineController
{
    public function __construct(
        private MachineRepository $machineRepository,
        private MachineStateChangedEventDispatcher $machineStateChangedEventDispatcher,
    ) {}

    /**
     * @throws ExceptionInterface
     */
    #[Route(methods: ['POST'])]
    public function create(
        CreateMachineRequest $request,
        FindMachineForCreationDispatcherInterface $messageDispatcher,
    ): JsonResponse {
        $machine = $this->machineRepository->find($request->id);
        if ($machine instanceof Machine) {
            if (!$machine->getState()->isResettable()) {
                return BadMachineCreateRequestResponse::createIdTakenResponse();
            }
        } else {
            $machine = new Machine($request->id);
        }

        $this->machineStateChangedEventDispatcher->dispatch($machine, MachineState::CREATE_RECEIVED);
        $messageDispatcher->dispatchForMachine($machine);

        return new JsonResponse(new MachineModel($machine), 202);
    }

    /**
     * @param non-empty-string $id
     *
     * @throws ExceptionInterface
     */
    #[Route(methods: ['GET', 'HEAD'])]
    public function status(
        string $id,
        FindMachineForRetrievalDispatcherInterface $messageDispatcher,
        ActionFailureRepository $actionFailureRepository,
    ): JsonResponse {
        $machine = $this->machineRepository->find($id);
        if (!$machine instanceof Machine) {
            $machine = new Machine($id);

            $this->machineStateChangedEventDispatcher->dispatch($machine, MachineState::FIND_RECEIVED);
            $messageDispatcher->dispatchForMachine($machine);
        }

        return new JsonResponse(new MachineModel($machine, $actionFailureRepository->find($id)));
    }

    /**
     * @param non-empty-string $id
     *
     * @throws ExceptionInterface
     */
    #[Route(methods: ['DELETE'])]
    public function delete(
        string $id,
        DeleteMachineDispatcherInterface $messageDispatcher,
    ): JsonResponse {
        $machine = $this->machineRepository->find($id);
        if (!$machine instanceof Machine) {
            $machine = new Machine($id);
        }

        $this->machineStateChangedEventDispatcher->dispatch($machine, MachineState::DELETE_RECEIVED);
        $messageDispatcher->dispatchForMachine($machine);

        return new JsonResponse(new MachineModel($machine), 202);
    }
}
