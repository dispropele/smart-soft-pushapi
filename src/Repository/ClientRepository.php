<?php

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * Find client by full name and passport number
     */
    public function findByCredentials(string $fullName, string $passportNumber): ?Client
    {
        return $this->createQueryBuilder('c')
            ->where('c.fullName = :fullName')
            ->andWhere('c.passportNumber = :passportNumber')
            ->setParameter('fullName', $fullName)
            ->setParameter('passportNumber', $passportNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(Client $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Client $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
