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
        int    $perPage,
        ?float $priceMin = null,
        ?float $priceMax = null,
        ?int   $categoryId = null,
        ?int   $goodTypeId = null
    ): array {
        $qb = $this->createQueryBuilder('g')
            ->select('g')
            ->leftJoin('g.merchant', 'm')
            ->leftJoin('m.city', 'c')
            ->leftJoin('g.category', 'cat')
            ->leftJoin('g.goodType', 'gt')
            // Только активные товары на витрине
            ->where('g.status = :status')
            ->setParameter('status', Good::STATUS_ACTIVE);

        if ($search !== '') {
            $qb->andWhere('LOWER(g.name) LIKE :q OR LOWER(m.name) LIKE :q OR LOWER(c.name) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($search) . '%');
        }

        if ($priceMin !== null) {
            $qb->andWhere('g.soldPrice >= :priceMin')
                ->setParameter('priceMin', $priceMin);
        }

        if ($priceMax !== null) {
            $qb->andWhere('g.soldPrice <= :priceMax')
                ->setParameter('priceMax', $priceMax);
        }

        if ($categoryId !== null) {
            $qb->andWhere('g.category = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        if ($goodTypeId !== null) {
            $qb->andWhere('g.goodType = :goodTypeId')
                ->setParameter('goodTypeId', $goodTypeId);
        }

        if ($orderField === 'soldPrice') {
            $qb->orderBy('g.soldPrice', $orderDir);
        } else {
            $qb->orderBy("g.$orderField", $orderDir);
        }

        return $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function countForCatalog(
        string $search,
        ?float $priceMin = null,
        ?float $priceMax = null,
        ?int   $categoryId = null,
        ?int   $goodTypeId = null
    ): int {
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

        if ($priceMin !== null) {
            $qb->andWhere('g.soldPrice >= :priceMin')
                ->setParameter('priceMin', $priceMin);
        }

        if ($priceMax !== null) {
            $qb->andWhere('g.soldPrice <= :priceMax')
                ->setParameter('priceMax', $priceMax);
        }

        if ($categoryId !== null) {
            $qb->andWhere('g.category = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        if ($goodTypeId !== null) {
            $qb->andWhere('g.goodType = :goodTypeId')
                ->setParameter('goodTypeId', $goodTypeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
