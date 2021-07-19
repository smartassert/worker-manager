<?php

namespace App\Services\Entity\Store;

use App\Entity\CreateFailure;

class CreateFailureStore extends AbstractEntityStore
{
    public function find(string $machineId): ?CreateFailure
    {
        $entity = $this->entityManager->find(CreateFailure::class, $machineId);

        return $entity instanceof CreateFailure ? $entity : null;
    }

    public function store(CreateFailure $entity): void
    {
        $this->doStore($entity);
    }
}
