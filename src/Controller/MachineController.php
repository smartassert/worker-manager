<?php

namespace App\Controller;

use App\Entity\CreateFailure;
use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Model\ProviderInterface;
use App\Repository\CreateFailureRepository;
use App\Repository\MachineRepository;
use App\Response\BadMachineCreateRequestResponse;
use App\Services\Entity\Store\MachineProviderStore;
use App\Services\Entity\Store\MachineStore;
use App\Services\MachineRequestDispatcher;
use App\Services\MachineRequestFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MachineController
{
    public const PATH_COMPONENT_ID = '{id}';
    public const PATH_MACHINE = '/' . self::PATH_COMPONENT_ID . '/machine';

    public function __construct(
        private MachineStore $machineStore,
        private MachineRequestDispatcher $machineRequestDispatcher,
        private MachineRequestFactory $machineRequestFactory,
        private readonly MachineRepository $machineRepository,
    ) {
    }

    #[Route(self::PATH_MACHINE, name: 'machine-create', methods: ['POST'])]
    public function create(string $id, MachineProviderStore $machineProviderStore): Response
    {
        $machine = $this->machineRepository->find($id);
        if ($machine instanceof Machine && !in_array($machine->getState(), Machine::RESETTABLE_STATES)) {
            return BadMachineCreateRequestResponse::createIdTakenResponse();
        }

        $this->machineStore->store(new Machine($id));
        $machineProviderStore->store(new MachineProvider($id, ProviderInterface::NAME_DIGITALOCEAN));
        $this->machineRequestDispatcher->dispatch(
            $this->machineRequestFactory->createFindThenCreate($id)
        );

        return new Response('', 202);
    }

    #[Route(self::PATH_MACHINE, name: 'machine-status', methods: ['GET', 'HEAD'])]
    public function status(string $id, CreateFailureRepository $createFailureRepository): Response
    {
        $machine = $this->machineRepository->find($id);
        if (!$machine instanceof Machine) {
            $machine = new Machine($id, Machine::STATE_FIND_RECEIVED);
            $this->machineStore->store($machine);

            $this->machineRequestDispatcher->dispatch(
                $this->machineRequestFactory->createFindThenCheckIsActive($id)
            );
        }

        $responseData = $machine->jsonSerialize();

        $createFailure = $createFailureRepository->find($id);
        if ($createFailure instanceof CreateFailure) {
            $responseData['create_failure'] = $createFailure->jsonSerialize();
        }

        return new JsonResponse($responseData);
    }

    #[Route(self::PATH_MACHINE, name: 'machine-delete', methods: ['DELETE'])]
    public function delete(string $id): Response
    {
        $machine = $this->machineRepository->find($id);
        if (false === $machine instanceof Machine) {
            $machine = new Machine($id, Machine::STATE_DELETE_RECEIVED);
            $this->machineStore->store($machine);
        }

        $this->machineRequestDispatcher->dispatch(
            $this->machineRequestFactory->createDelete($id)
        );

        return new Response('', 202);
    }
}
