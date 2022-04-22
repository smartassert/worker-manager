<?php

declare(strict_types=1);

namespace App\Tests\Services;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class EntityRemover
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param class-string $className
     */
    public function removeAllForEntity(string $className): void
    {
        $repository = $this->entityManager->getRepository($className);

        if ($repository instanceof EntityRepository) {
            $entities = $repository->findAll();

            foreach ($entities as $entity) {
                $this->entityManager->remove($entity);
            }

            $this->entityManager->flush();
        }
    }
}
