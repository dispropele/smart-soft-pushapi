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
            ->addSelect('cat', 'gt', 'pin', 'ins', 'ms', 'met', 'img')
            ->leftJoin('p.category', 'cat')
            ->leftJoin('p.goodType', 'gt')
            ->leftJoin('p.itemInserts', 'pin')
            ->leftJoin('pin.insert', 'ins')
            ->leftJoin('p.metalStandard', 'ms')
            ->leftJoin('ms.metal', 'met')
            ->leftJoin('p.images', 'img')
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
            ->leftJoin('p.itemInserts', 'pin')
            ->leftJoin('pin.insert', 'ins')
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

    /** Сумма оценки/продажи для статуса */
    public function getSumForStatus(string $status): float
    {
        $field = ($status === PledgedItem::STATUS_FOR_SALE) ? 'p.soldPrice' : 'p.estimatedValue';

        $result = $this->createQueryBuilder('p')
            ->select("SUM($field)")
            ->where('p.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $result;
    }

    /** Последние продажи */
    public function findLatestSales(int $limit): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', PledgedItem::STATUS_SOLD)
            ->orderBy('p.statusDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Сумма продаж за последние N дней, по датам */
    public function getSalesByDayLastWeek(): array
    {
        try {
            $conn = $this->getEntityManager()->getConnection();
            $rows = $conn->executeQuery(
                "SELECT DATE(status_date) as date, SUM(sold_price) as amount
                FROM pledged_items
                WHERE status = 'sold'
                AND status_date IS NOT NULL
                AND status_date >= NOW() - INTERVAL '7 days'
                GROUP BY DATE(status_date)
                ORDER BY date ASC"
            )->fetchAllAssociative();

            return array_map(fn($r) => [
                'date'   => (string) $r['date'],
                'amount' => (float) ($r['amount'] ?? 0),
            ], $rows);
        } catch (\Exception $e) {
            return [];
        }
    }

    /** Форматирование даты для совместимости с разными драйверами */
    private function formatDate($date): string
    {
        if ($date instanceof \DateTime) {
            return $date->format('Y-m-d');
        }
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }
        return (string)$date;
    }
}


