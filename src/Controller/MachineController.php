<?php

namespace App\Controller;

use App\Entity\CreateFailure;
use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Model\ProviderInterface;
use App\Repository\CreateFailureRepository;
use App\Repository\MachineProviderRepository;
use App\Repository\MachineRepository;
use App\Response\BadMachineCreateRequestResponse;
use App\Response\MachineRequestResponse;
use App\Services\MachineRequestDispatcher;
use App\Services\MachineRequestFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class MachineController
{
    public const PATH_COMPONENT_ID = '{id}';
    public const PATH_MACHINE = '/' . self::PATH_COMPONENT_ID . '/machine';

    public function __construct(
        private MachineRequestDispatcher $machineRequestDispatcher,
        private MachineRequestFactory $machineRequestFactory,
        private readonly MachineRepository $machineRepository,
        private readonly RouterInterface $router,
    ) {
    }

    #[Route(self::PATH_MACHINE, name: 'machine-create', methods: ['POST'])]
    public function create(string $id, MachineProviderRepository $machineProviderRepository): Response
    {
        $machine = $this->machineRepository->find($id);
        if ($machine instanceof Machine) {
            if (!in_array($machine->getState(), Machine::RESETTABLE_STATES)) {
                return BadMachineCreateRequestResponse::createIdTakenResponse();
            }
        } else {
            $machine = new Machine($id);
        }

        $machine->setState(Machine::STATE_CREATE_RECEIVED);
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

        return new MachineRequestResponse($id, 'create', $this->router->generate('machine-status', ['id' => $id]));
    }

    #[Route(self::PATH_MACHINE, name: 'machine-status', methods: ['GET', 'HEAD'])]
    public function status(string $id, CreateFailureRepository $createFailureRepository): Response
    {
        $machine = $this->machineRepository->find($id);
        if (!$machine instanceof Machine) {
            $machine = new Machine($id, Machine::STATE_FIND_RECEIVED);
            $this->machineRepository->add($machine);

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
        if (!$machine instanceof Machine) {
            $this->machineRepository->add(new Machine($id, Machine::STATE_DELETE_RECEIVED));
        }

        $this->machineRequestDispatcher->dispatch(
            $this->machineRequestFactory->createDelete($id)
        );

        return new MachineRequestResponse($id, 'delete', $this->router->generate('machine-status', ['id' => $id]));
    }
}
