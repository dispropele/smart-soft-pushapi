<?php

namespace App\Repository;

use App\Entity\LoanTicket;
use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoanTicket>
 */
class LoanTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanTicket::class);
    }

    /**
     * Find all open tickets for a client
     */
    public function findOpenByClient(Client $client): array
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.client = :client')
            ->andWhere('lt.status = :status')
            ->setParameter('client', $client)
            ->setParameter('status', LoanTicket::STATUS_OPEN)
            ->orderBy('lt.issuedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find ticket by number
     */
    public function findByNumber(string $ticketNumber): ?LoanTicket
    {
        return $this->findOneBy(['ticketNumber' => $ticketNumber]);
    }

    /**
     * Find ticket by number and client full name
     */
    public function findByTicketAndClient(string $ticketNumber, string $fullName): ?LoanTicket
    {
        return $this->createQueryBuilder('lt')
            ->innerJoin('lt.client', 'c')
            ->where('lt.ticketNumber = :ticket')
            ->andWhere('c.fullName = :fullName')
            ->setParameter('ticket', $ticketNumber)
            ->setParameter('fullName', $fullName)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(LoanTicket $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(LoanTicket $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
