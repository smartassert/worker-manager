<?php

namespace App\Services\ServiceStatusInspector;

use App\Entity\CreateFailure;
use App\Entity\Machine;
use App\Entity\MachineProvider;
use Doctrine\ORM\EntityManagerInterface;

class DatabaseInspector implements ComponentInspectorInterface
{
    public const ENTITY_CLASS_NAMES = [
        CreateFailure::class,
        Machine::class,
        MachineProvider::class,
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(): void
    {
        foreach (self::ENTITY_CLASS_NAMES as $entityClassName) {
            $this->entityManager->getRepository($entityClassName);
        }
    }
}
