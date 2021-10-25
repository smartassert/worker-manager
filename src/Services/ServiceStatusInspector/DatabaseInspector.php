<?php

namespace App\Services\ServiceStatusInspector;

use Doctrine\ORM\EntityManagerInterface;

class DatabaseInspector implements ComponentInspectorInterface
{
    /**
     * @param array<class-string> $entityClassNames
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private array $entityClassNames,
    ) {
    }

    public function __invoke(): void
    {
        foreach ($this->entityClassNames as $entityClassName) {
            $this->entityManager->getRepository($entityClassName);
        }
    }
}
