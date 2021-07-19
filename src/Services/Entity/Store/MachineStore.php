<?php

namespace App\Services\Entity\Store;

use App\Entity\Machine;

class MachineStore extends AbstractEntityStore
{
    public function find(string $machineId): ?Machine
    {
        $entity = $this->entityManager->find(Machine::class, $machineId);

        return $entity instanceof Machine ? $entity : null;
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
        $existingEntity = $this->find($entity->getId());
        if ($existingEntity instanceof Machine) {
            $entity = $existingEntityHandler($entity, $existingEntity);
        }

        $this->doStore($entity);
    }
}
