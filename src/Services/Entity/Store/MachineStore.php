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
        $this->save($entity, function (Machine $entity, Machine $existingEntity) {
            return $existingEntity->merge($entity);
        });
    }

    public function persist(Machine $entity): void
    {
        $this->save($entity, function (Machine $entity, Machine $existingEntity) {
            return $existingEntity;
        });
    }

    private function save(Machine $entity, callable $existingEntityHandler): void
    {
        $existingEntity = $this->machineRepository->find($entity->getId());
        if ($existingEntity instanceof Machine) {
            $entity = $existingEntityHandler($entity, $existingEntity);
        }

        $this->doStore($entity);
    }
}
