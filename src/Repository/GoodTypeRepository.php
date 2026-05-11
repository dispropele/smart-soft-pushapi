<?php

namespace App\Repository;

use App\Entity\GoodType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GoodTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoodType::class);
    }

    public function findByCategory($categoryId): array
    {
        return $this->createQueryBuilder('gt')
            ->where('gt.category = :category')
            ->setParameter('category', $categoryId)
            ->orderBy('gt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
