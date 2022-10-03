<?php

namespace App\Repository;

use App\Entity\MachineProvider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MachineProvider>
 *
 * @method null|MachineProvider find($id, $lockMode = null, $lockVersion = null)
 * @method null|MachineProvider findOneBy(array $criteria, array $orderBy = null)
 * @method MachineProvider[]    findAll()
 * @method MachineProvider[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MachineProviderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MachineProvider::class);
    }

    public function add(MachineProvider $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }
}
