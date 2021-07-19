<?php

namespace App\Repository;

use App\Entity\MessageState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method null|MessageState find($id, $lockMode = null, $lockVersion = null)
 * @method null|MessageState findOneBy(array $criteria, array $orderBy = null)
 * @method MessageState[]    findAll()
 * @method MessageState[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageState::class);
    }
}
