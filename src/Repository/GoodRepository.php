<?php

namespace App\Repository;

use App\Entity\Good;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GoodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Good::class);
    }

    public function findForCatalog(
        string $search,
        string $orderField,
        string $orderDir,
        int    $page,
        int    $perPage
    ): array {
        $qb = $this->createQueryBuilder('g')
            ->leftJoin('g.merchant', 'm')->addSelect('m')
            ->leftJoin('m.city', 'c')->addSelect('c')
            ->leftJoin('g.images', 'i')->addSelect('i')
            ->leftJoin('g.category', 'cat')->addSelect('cat')
            ->leftJoin('g.metalStandard', 'ms')->addSelect('ms')
            ->leftJoin('ms.metal', 'mt')->addSelect('mt')
            ->leftJoin('g.currency', 'cur')->addSelect('cur')
            // Только активные товары на витрине
            ->where('g.status = :status')
            ->setParameter('status', Good::STATUS_ACTIVE);

        if ($search !== '') {
            $qb->andWhere('LOWER(g.name) LIKE :q OR LOWER(m.name) LIKE :q OR LOWER(c.name) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($search) . '%');
        }

        if ($orderField === 'soldPrice') {
            $qb->orderBy('CAST(g.soldPrice AS DECIMAL)', $orderDir);
        } else {
            $qb->orderBy("g.$orderField", $orderDir);
        }

        return $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function countForCatalog(string $search): int
    {
        $qb = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->leftJoin('g.merchant', 'm')
            ->leftJoin('m.city', 'c')
            ->where('g.status = :status')
            ->setParameter('status', Good::STATUS_ACTIVE);

        if ($search !== '') {
            $qb->andWhere('LOWER(g.name) LIKE :q OR LOWER(m.name) LIKE :q OR LOWER(c.name) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($search) . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
