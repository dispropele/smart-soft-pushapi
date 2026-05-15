<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\LoanTicket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LoanTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanTicket::class);
    }

    /** Активные билеты (open + grace) по клиенту */
    public function findActiveByClient(Client $client): array
    {
        $cid = $client->getId();
        if ($cid === null) {
            return [];
        }

        return $this->createQueryBuilder('lt')
            ->innerJoin('lt.client', 'c')
            ->where('c.id = :cid')
            ->andWhere('lt.status IN (:open, :grace)')
            ->setParameter('cid', $cid)
            ->setParameter('open', LoanTicket::STATUS_OPEN)
            ->setParameter('grace', LoanTicket::STATUS_GRACE)
            ->orderBy('lt.returnDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** История (закрытые, просроченные, перезалоги) по клиенту */
    public function findHistoryByClient(Client $client): array
    {
        $cid = $client->getId();
        if ($cid === null) {
            return [];
        }

        return $this->createQueryBuilder('lt')
            ->innerJoin('lt.client', 'c')
            ->where('c.id = :cid')
            ->andWhere('lt.status IN (:closed, :expired, :repledged)')
            ->setParameter('cid', $cid)
            ->setParameter('closed', LoanTicket::STATUS_CLOSED)
            ->setParameter('expired', LoanTicket::STATUS_EXPIRED)
            ->setParameter('repledged', LoanTicket::STATUS_REPLEDGED)
            ->orderBy('lt.closedAt', 'DESC')
            ->addOrderBy('lt.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @deprecated use findActiveByClient */
    public function findOpenByClient(Client $client): array
    {
        return $this->findActiveByClient($client);
    }

    public function findByNumber(string $ticketNumber): ?LoanTicket
    {
        return $this->findOneBy(['ticketNumber' => trim($ticketNumber)]);
    }

    public function findByTicketAndClient(string $ticketNumber, string $fullName): ?LoanTicket
    {
        $ticketNumber = trim($ticketNumber);
        $fullName = trim(preg_replace('/\s+/u', ' ', $fullName));

        return $this->createQueryBuilder('lt')
            ->innerJoin('lt.client', 'c')
            ->where('lt.ticketNumber = :ticket')
            ->andWhere('LOWER(c.fullName) = LOWER(:fullName)')
            ->setParameter('ticket', $ticketNumber)
            ->setParameter('fullName', $fullName)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Все просроченные, где grace тоже истёк — для перевода на реализацию */
    public function findForForcedSale(): array
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.status = :grace')
            ->setParameter('grace', LoanTicket::STATUS_GRACE)
            ->getQuery()
            ->getResult();
    }

    /** Сумма займов в определённом статусе */
    public function getSumForStatus(string $status): float
    {
        $result = $this->createQueryBuilder('lt')
            ->select('SUM(lt.loanAmount)')
            ->where('lt.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $result;
    }

    /** Сумма выданных займов за последние N дней, по датам (с возвращением массива по дням) */
    public function getIssuedByDayLastWeek(): array
    {
        try {
            $sevenDaysAgo = (new \DateTime())->modify('-7 days')->format('Y-m-d');
            
            $results = $this->createQueryBuilder('lt')
                ->select("CAST(lt.issuedAt AS date) as date, SUM(lt.loanAmount) as amount")
                ->where('lt.issuedAt >= :weekAgo')
                ->setParameter('weekAgo', $sevenDaysAgo)
                ->groupBy('date')
                ->orderBy('date', 'ASC')
                ->getQuery()
                ->getResult();

            return array_map(fn($r) => [
                'date' => $this->formatDate($r['date']),
                'amount' => (float)($r['amount'] ?? 0)
            ], $results);
        } catch (\Exception $e) {
            return [];
        }
    }

    /** Сумма погашенных займов за последние N дней (закрытые билеты), по датам
     *  ВАЖНО: включает основной долг + собранные проценты, которые фактически получены */
    public function getClosedByDayLastWeek(): array
    {
        try {
            $sevenDaysAgo = (new \DateTime())->modify('-7 days')->format('Y-m-d');
            
            $results = $this->createQueryBuilder('lt')
                ->select("CAST(lt.closedAt AS date) as date, 
                         SUM(lt.paidPrincipal + lt.paidInterest) as amount")
                ->where('lt.closedAt IS NOT NULL')
                ->andWhere('lt.closedAt >= :weekAgo')
                ->setParameter('weekAgo', $sevenDaysAgo)
                ->groupBy('date')
                ->orderBy('date', 'ASC')
                ->getQuery()
                ->getResult();

            return array_map(fn($r) => [
                'date' => $this->formatDate($r['date']),
                'amount' => (float)($r['amount'] ?? 0)
            ], $results);
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


