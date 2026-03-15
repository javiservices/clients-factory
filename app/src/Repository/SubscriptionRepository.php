<?php

namespace App\Repository;

use App\Entity\Subscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    //    /**
    //     * @return Subscription[] Returns an array of Subscription objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Subscription
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /** Suscripciones activas que vencen en los próximos $days días */
    public function findExpiringSoon(int $days = 7): array
    {
        $now = new \DateTime();
        $future = (new \DateTime())->modify('+' . $days . ' days');

        return $this->createQueryBuilder('s')
            ->where('s.isActive = :active')
            ->andWhere('s.endDate BETWEEN :now AND :future')
            ->andWhere('s.frozenAt IS NULL')
            ->setParameter('active', true)
            ->setParameter('now', $now)
            ->setParameter('future', $future)
            ->orderBy('s.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
