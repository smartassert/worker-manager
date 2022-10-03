<?php

namespace App\Repository;

use App\Entity\CreateFailure;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CreateFailure>
 *
 * @method null|CreateFailure find($id, $lockMode = null, $lockVersion = null)
 * @method null|CreateFailure findOneBy(array $criteria, array $orderBy = null)
 * @method CreateFailure[]    findAll()
 * @method CreateFailure[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CreateFailureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CreateFailure::class);
    }

    public function add(CreateFailure $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }
}
