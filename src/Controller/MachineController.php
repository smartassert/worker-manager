<?php

namespace App\Controller;

use App\Entity\CreateFailure;
use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Enum\MachineState;
use App\Model\FailedCreationMachine;
use App\Model\ProviderInterface;
use App\Repository\CreateFailureRepository;
use App\Repository\MachineProviderRepository;
use App\Repository\MachineRepository;
use App\Response\BadMachineCreateRequestResponse;
use App\Services\MachineRequestDispatcher;
use App\Services\MachineRequestFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

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

    #[Route(self::PATH_MACHINE, name: 'machine-create', methods: ['POST'])]
    public function create(string $id, MachineProviderRepository $machineProviderRepository): JsonResponse
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

        $machineProvider = $machineProviderRepository->find($id);
        if ($machineProvider instanceof MachineProvider) {
            $machineProvider->setName(ProviderInterface::NAME_DIGITALOCEAN);
        } else {
            $machineProvider = new MachineProvider($id, ProviderInterface::NAME_DIGITALOCEAN);
        }
        $machineProviderRepository->add($machineProvider);

        $this->machineRequestDispatcher->dispatch(
            $this->machineRequestFactory->createFindThenCreate($id)
        );

        return new JsonResponse($machine, 202);
    }

    #[Route(self::PATH_MACHINE, name: 'machine-status', methods: ['GET', 'HEAD'])]
    public function status(string $id, CreateFailureRepository $createFailureRepository): JsonResponse
    {
        $machine = $this->machineRepository->find($id);
        if (!$machine instanceof Machine) {
            $machine = new Machine($id, MachineState::FIND_RECEIVED);
            $this->machineRepository->add($machine);

            $this->machineRequestDispatcher->dispatch(
                $this->machineRequestFactory->createFindThenCheckIsActive($id)
            );
        }

        $createFailure = $createFailureRepository->find($id);
        if ($createFailure instanceof CreateFailure) {
            $machine = new FailedCreationMachine($machine, $createFailure);
        }

        return new JsonResponse($machine);
    }

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

        return new JsonResponse($machine, 202);
    }
}
