<?php

namespace App\Repository;

use App\Entity\BillingRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BillingRecord>
 */
class BillingRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BillingRecord::class);
    }

    //    /**
    //     * @return BillingRecord[] Returns an array of BillingRecord objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?BillingRecord
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * @return BillingRecord[] Returns an array of BillingRecord objects
     */
    public function findByClientAndPeriod($client, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.client = :client')
            ->andWhere('b.billingDate >= :startDate')
            ->andWhere('b.billingDate <= :endDate')
            ->andWhere('b.invoice IS NULL')
            ->andWhere('b.isBilled = false')
            ->setParameter('client', $client)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('b.billingDate', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return BillingRecord[] Returns an array of BillingRecord objects
     */
    public function findByInvoice($invoice): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.invoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->orderBy('b.billingDate', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
