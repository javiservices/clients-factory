<?php

namespace App\Repository;

use App\Entity\AccessLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AccessLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccessLog::class);
    }

    public function findByDate(\DateTime $date): array
    {
        $startDate = clone $date;
        $startDate->setTime(0, 0, 0);
        
        $endDate = clone $date;
        $endDate->setTime(23, 59, 59);

        return $this->createQueryBuilder('a')
            ->where('a.accessTime BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('a.accessTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Accesos agrupados por día desde $from hasta hoy */
    public function countAccessesByDay(\DateTime $from): array
    {
        $logs = $this->createQueryBuilder('a')
            ->where('a.accessTime >= :from')
            ->andWhere('a.status = :status')
            ->setParameter('from', $from)
            ->setParameter('status', 'success')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($logs as $log) {
            $day = $log->getAccessTime()->format('Y-m-d');
            $data[$day] = ($data[$day] ?? 0) + 1;
        }
        ksort($data);
        return $data;
    }

    /** Distribución de accesos por hora del día (0-23) */
    public function countByHour(): array
    {
        $logs = $this->createQueryBuilder('a')
            ->where('a.status = :status')
            ->setParameter('status', 'success')
            ->getQuery()
            ->getResult();

        $data = array_fill(0, 24, 0);
        foreach ($logs as $log) {
            $hour = (int) $log->getAccessTime()->format('G');
            $data[$hour]++;
        }
        return $data;
    }

    /** Total de accesos por estado */
    public function countByStatus(): array
    {
        $logs = $this->createQueryBuilder('a')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($logs as $log) {
            $s = $log->getStatus() ?? 'unknown';
            $data[$s] = ($data[$s] ?? 0) + 1;
        }
        return $data;
    }

    /** Accesos exitosos de hoy */
    public function countToday(): int
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.accessTime >= :today')
            ->andWhere('a.status = :status')
            ->setParameter('today', $today)
            ->setParameter('status', 'success')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Últimos N accesos de un cliente concreto */
    public function findRecentByClient(\App\Entity\Client $client, int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.Client = :client')
            ->setParameter('client', $client)
            ->orderBy('a.accessTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}