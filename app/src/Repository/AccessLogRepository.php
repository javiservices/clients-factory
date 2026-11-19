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

    /**
     * Cuenta cuántos accesos exitosos ha tenido un cliente durante el día actual.
     */
    public function countTodaySuccessByClient(\App\Entity\Client $client): int
    {
        $todayStart = new \DateTime('today 00:00:00');
        $todayEnd = new \DateTime('today 23:59:59');

        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.Client = :client')
            ->andWhere('a.status = :status')
            ->andWhere('a.accessTime >= :start')
            ->andWhere('a.accessTime <= :end')
            ->setParameter('client', $client)
            ->setParameter('status', 'success')
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Calcula el aforo en sala teniendo en cuenta sesiones de entrenamiento estimadas (80 minutos).
     * Si un mismo socio sale y vuelve a entrar dentro de esos 80 minutos, se considera
     * la misma sesión de entrenamiento: no se suma como un segundo socio ni se alarga
     * artificialmente el tiempo de la sesión original.
     */
    public function calculateCurrentOccupancy(int $sessionMinutes = 80): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $now = new \DateTime();
        $lookback = (clone $now)->modify('-' . ($sessionMinutes * 2) . ' minutes');

        $rows = $conn->fetchAllAssociative('
            SELECT client_id, access_time 
            FROM access_log 
            WHERE status = \'success\' 
              AND access_time >= :lookback
            ORDER BY client_id ASC, access_time ASC
        ', ['lookback' => $lookback->format('Y-m-d H:i:s')]);

        if (empty($rows)) {
            return 0;
        }

        $clientAccesses = [];
        foreach ($rows as $row) {
            $clientAccesses[$row['client_id']][] = new \DateTime($row['access_time']);
        }

        $activeCount = 0;

        foreach ($clientAccesses as $clientId => $times) {
            $currentSessionStart = null;
            $currentSessionEnd = null;

            foreach ($times as $accessTime) {
                if ($currentSessionStart === null || $accessTime > $currentSessionEnd) {
                    $currentSessionStart = $accessTime;
                    $currentSessionEnd = (clone $accessTime)->modify("+{$sessionMinutes} minutes");
                }
            }

            if ($currentSessionEnd !== null && $now >= $currentSessionStart && $now <= $currentSessionEnd) {
                $activeCount++;
            }
        }

        return $activeCount;
    }

    /**
     * Devuelve el aforo con estado cualitativo (bajo, medio, alto), porcentaje y textos informativos.
     */
    public function getOccupancyDetails(int $sessionMinutes = 80, int $capacityLimit = 45): array
    {
        $count = $this->calculateCurrentOccupancy($sessionMinutes);

        $level = 'low';
        $status = 'Baja afluencia';
        $statusSub = 'Momento ideal para entrenar';
        $color = '#10B981'; // Verde

        if ($count >= 30) {
            $level = 'high';
            $status = 'Sala concurrida';
            $statusSub = 'Afluencia alta en este momento';
            $color = '#EF4444'; // Rojo
        } elseif ($count >= 15) {
            $level = 'medium';
            $status = 'Afluencia moderada';
            $statusSub = 'Buen ambiente y máquinas libres';
            $color = '#F59E0B'; // Ámbar
        }

        $percentage = min(100, max(6, (int) round(($count / $capacityLimit) * 100)));

        return [
            'count'          => $count,
            'level'          => $level,
            'status'         => $status,
            'statusSub'      => $statusSub,
            'color'          => $color,
            'percentage'     => $percentage,
            'sessionMinutes' => $sessionMinutes,
            'capacityLimit'  => $capacityLimit,
            'updatedAt'      => (new \DateTime())->format('H:i'),
        ];
    }
}