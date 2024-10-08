<?php

namespace App\Controller;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Model\Machine as MachineModel;
use App\Repository\ActionFailureRepository;
use App\Repository\MachineRepository;
use App\Response\BadMachineCreateRequestResponse;
use App\Services\MachineRequestDispatcher;
use App\Services\MachineRequestFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

class MachineController
{
    public const PATH_COMPONENT_ID = '{id}';
    public const PATH_MACHINE = '/machine/' . self::PATH_COMPONENT_ID;

    public function __construct(
        private readonly MachineRequestDispatcher $machineRequestDispatcher,
        private readonly MachineRequestFactory $machineRequestFactory,
        private readonly MachineRepository $machineRepository,
    ) {
    }

    /**
     * @param non-empty-string $id
     *
     * @throws ExceptionInterface
     */
    #[Route(self::PATH_MACHINE, name: 'machine-create', methods: ['POST'])]
    public function create(string $id): JsonResponse
    {
        $machine = $this->machineRepository->find($id);
        if ($machine instanceof Machine) {
            if (!in_array($machine->getState(), MachineState::RESETTABLE_STATES)) {
                return BadMachineCreateRequestResponse::createIdTakenResponse();
            }
        } else {
            $machine = new Machine($id);
        }

        $machine->setState(MachineState::CREATE_RECEIVED);
        $this->machineRepository->add($machine);

        $this->machineRequestDispatcher->dispatch(
            $this->machineRequestFactory->createFindThenCreate($id)
        );

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
            $machine = new Machine($id, MachineState::FIND_RECEIVED);
            $this->machineRepository->add($machine);

            $this->machineRequestDispatcher->dispatch(
                $this->machineRequestFactory->createFindThenCheckIsActive($id)
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

        $machine->setState(MachineState::DELETE_RECEIVED);
        $this->machineRepository->add($machine);

        $this->machineRequestDispatcher->dispatch(
            $this->machineRequestFactory->createDelete($id)
        );

        return new JsonResponse(new MachineModel($machine), 202);
    }
}
