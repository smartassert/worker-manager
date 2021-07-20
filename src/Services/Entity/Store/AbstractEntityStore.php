<?php

namespace App\Services\Entity\Store;

use App\Entity\CreateFailure;
use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Entity\MessageState;
use Doctrine\ORM\EntityManagerInterface;

abstract class AbstractEntityStore
{
    public function __construct(
        protected EntityManagerInterface $entityManager
    ) {
    }

    protected function doStore(CreateFailure | Machine | MachineProvider | MessageState $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }
}
