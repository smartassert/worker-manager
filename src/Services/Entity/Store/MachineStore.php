<?php

namespace App\Services\Entity\Store;

use App\Entity\Machine;
use App\Repository\MachineRepository;
use Doctrine\ORM\EntityManagerInterface;

class MachineStore extends AbstractEntityStore
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly MachineRepository $machineRepository,
    ) {
        parent::__construct($entityManager);
    }

    public function store(Machine $entity): void
    {
        $existingEntity = $this->machineRepository->find($entity->getId());
        if ($existingEntity instanceof Machine) {
            $entity = $existingEntity->merge($entity);
        }

        $this->doStore($entity);
    }
}
