<?php

namespace App\Services\Entity\Store;

use App\Entity\MachineProvider;
use App\Repository\MachineProviderRepository;
use Doctrine\ORM\EntityManagerInterface;

class MachineProviderStore extends AbstractEntityStore
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly MachineProviderRepository $machineProviderRepository,
    ) {
        parent::__construct($entityManager);
    }

    public function store(MachineProvider $entity): void
    {
        $existingEntity = $this->machineProviderRepository->find($entity->getId());
        if ($existingEntity instanceof MachineProvider) {
            $entity = $existingEntity->merge($entity);
        }

        $this->doStore($entity);
    }
}
