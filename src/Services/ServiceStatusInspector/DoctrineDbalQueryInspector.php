<?php

namespace App\Services\ServiceStatusInspector;

use Doctrine\DBAL\Driver\Exception as DbalDriverException;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineDbalQueryInspector
{
    /**
     * @param array<string, scalar> $queryParameters
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $query = 'SELECT 1',
        private array $queryParameters = []
    ) {
    }

    /**
     * @throws DbalDriverException
     * @throws DbalException
     */
    public function __invoke(): void
    {
        $connection = $this->entityManager->getConnection();

        $statement = $connection->prepare($this->query);
        $statement->execute($this->queryParameters);
    }
}
