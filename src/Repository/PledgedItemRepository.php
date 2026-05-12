<?php

namespace App\Repository;

use App\Entity\PledgedItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PledgedItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PledgedItem::class);
    }

    public function findForCatalog(
        string $search,
        string $orderField,
        string $orderDir,
        int    $page,
        int    $perPage,
        ?float $priceMin    = null,
        ?float $priceMax    = null,
        ?int   $categoryId  = null,
        ?int   $goodTypeId  = null
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'cat')
            ->leftJoin('p.goodType', 'gt')
            ->leftJoin('p.insert', 'ins')
            ->leftJoin('p.metalStandard', 'ms')
            ->leftJoin('ms.metal', 'met')
            ->where('p.status = :status')
            ->setParameter('status', PledgedItem::STATUS_FOR_SALE);

        if ($search !== '') {
            $qb->andWhere(
                'LOWER(p.name) LIKE :q OR (p.description IS NOT NULL AND LOWER(p.description) LIKE :q) OR ' .
                'LOWER(cat.name) LIKE :q OR LOWER(gt.name) LIKE :q OR LOWER(ins.name) LIKE :q OR LOWER(met.name) LIKE :q'
            )->setParameter('q', '%' . mb_strtolower($search) . '%');
        }
        if ($priceMin !== null) {
            $qb->andWhere('p.soldPrice >= :pmin')->setParameter('pmin', $priceMin);
        }
        if ($priceMax !== null) {
            $qb->andWhere('p.soldPrice <= :pmax')->setParameter('pmax', $priceMax);
        }
        if ($categoryId !== null) {
            $qb->andWhere('p.category = :cat')->setParameter('cat', $categoryId);
        }
        if ($goodTypeId !== null) {
            $qb->andWhere('p.goodType = :gt')->setParameter('gt', $goodTypeId);
        }

        $field = in_array($orderField, ['soldPrice', 'name', 'publishedAt']) ? $orderField : 'publishedAt';
        $qb->orderBy("p.$field", $orderDir);

        return $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function countForCatalog(
        string $search,
        ?float $priceMin   = null,
        ?float $priceMax   = null,
        ?int   $categoryId = null,
        ?int   $goodTypeId = null
    ): int {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->leftJoin('p.category', 'cat')
            ->leftJoin('p.goodType', 'gt')
            ->leftJoin('p.insert', 'ins')
            ->leftJoin('p.metalStandard', 'ms')
            ->leftJoin('ms.metal', 'met')
            ->where('p.status = :status')
            ->setParameter('status', PledgedItem::STATUS_FOR_SALE);

        if ($search !== '') {
            $qb->andWhere(
                'LOWER(p.name) LIKE :q OR (p.description IS NOT NULL AND LOWER(p.description) LIKE :q) OR ' .
                'LOWER(cat.name) LIKE :q OR LOWER(gt.name) LIKE :q OR LOWER(ins.name) LIKE :q OR LOWER(met.name) LIKE :q'
            )->setParameter('q', '%' . mb_strtolower($search) . '%');
        }
        if ($priceMin !== null) $qb->andWhere('p.soldPrice >= :pmin')->setParameter('pmin', $priceMin);
        if ($priceMax !== null) $qb->andWhere('p.soldPrice <= :pmax')->setParameter('pmax', $priceMax);
        if ($categoryId !== null) $qb->andWhere('p.category = :cat')->setParameter('cat', $categoryId);
        if ($goodTypeId !== null) $qb->andWhere('p.goodType = :gt')->setParameter('gt', $goodTypeId);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** Предметы по билету с заданным статусом */
    public function findByTicketAndStatus(int $ticketId, string $status): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.loanTicket = :tid')->setParameter('tid', $ticketId)
            ->andWhere('p.status = :s')->setParameter('s', $status)
            ->getQuery()->getResult();
    }
}