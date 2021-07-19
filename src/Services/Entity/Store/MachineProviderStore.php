<?php

namespace App\Services\Entity\Store;

use App\Entity\MachineProvider;

class MachineProviderStore extends AbstractEntityStore
{
    public function find(string $machineId): ?MachineProvider
    {
        $entity = $this->entityManager->find(MachineProvider::class, $machineId);

        return $entity instanceof MachineProvider ? $entity : null;
    }

    public function store(MachineProvider $entity): void
    {
        $existingEntity = $this->find($entity->getId());
        if ($existingEntity instanceof MachineProvider) {
            $entity = $existingEntity->merge($entity);
        }

        $this->doStore($entity);
    }
}
