<?php

namespace App\Repository;

use App\Entity\ActionFailure;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActionFailure>
 *
 * @method null|ActionFailure find($id, $lockMode = null, $lockVersion = null)
 * @method null|ActionFailure findOneBy(array $criteria, array $orderBy = null)
 * @method ActionFailure[]    findAll()
 * @method ActionFailure[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActionFailureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActionFailure::class);
    }

    public function add(ActionFailure $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }
}
