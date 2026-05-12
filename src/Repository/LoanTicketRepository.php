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
}
