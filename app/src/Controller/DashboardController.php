<?php

namespace App\Controller;

use App\Entity\Subscription;
use App\Repository\AccessLogRepository;
use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use App\Repository\SubscriptionRepository;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'dashboard', methods: ['GET'])]
    #[Route('/dashboard', name: 'dashboard_alias', methods: ['GET'])]
    public function dashboard(EntityManagerInterface $em): Response
    {
        $now = new \DateTime();
        $startOfMonth = new \DateTime('first day of this month 00:00:00');
        $endOfMonth = new \DateTime('last day of this month 23:59:59');

        $monthNames = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        $currentMonthName = $monthNames[(int)$now->format('n')] . ' ' . $now->format('Y');

        $conn = $em->getConnection();
        // Accesos del mes actual en access_log
        $accessRows = $conn->fetchAllAssociative("
            SELECT client_id, COUNT(*) as cnt 
            FROM access_log 
            WHERE access_time >= :start AND access_time <= :end AND status = 'success'
            GROUP BY client_id
        ", [
            'start' => $startOfMonth->format('Y-m-d H:i:s'),
            'end'   => $endOfMonth->format('Y-m-d H:i:s'),
        ]);
        $accessMap = [];
        foreach ($accessRows as $row) {
            $accessMap[(int)$row['client_id']] = (int)$row['cnt'];
        }

        // Socios con suscripción activa/pagada en el mes actual
        $subscriptions = $em->getRepository(Subscription::class)->createQueryBuilder('s')
            ->join('s.client', 'c')
            ->leftJoin('s.subscriptionType', 'st')
            ->where('s.isActive = true')
            ->andWhere('s.lastPaymentDate IS NOT NULL')
            ->andWhere('s.lastPaymentDate <= :end')
            ->andWhere('s.endDate >= :start')
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->getQuery()
            ->getResult();

        $candidates = [];
        $processed = [];
        foreach ($subscriptions as $sub) {
            $client = $sub->getClient();
            if (!$client || isset($processed[$client->getId()])) {
                continue;
            }
            $processed[$client->getId()] = true;
            $monthAccesses = $accessMap[$client->getId()] ?? 0;
            $paidMonth = $sub->getSubscriptionType()?->getPrice() ?? 50.0;
            $candidates[] = [
                'client'         => $client,
                'paidMonth'      => $paidMonth,
                'monthAccesses'  => $monthAccesses,
                'historicalPaid' => (float) $client->getTotalPaid(),
            ];
        }

        usort($candidates, function (array $a, array $b) {
            // 1º Menos accesos en el mes actual
            if ($a['monthAccesses'] !== $b['monthAccesses']) {
                return $a['monthAccesses'] <=> $b['monthAccesses'];
            }
            // 2º Desempate: mayor aportación histórica
            if ($a['historicalPaid'] !== $b['historicalPaid']) {
                return $b['historicalPaid'] <=> $a['historicalPaid'];
            }
            return $a['client']->getId() <=> $b['client']->getId();
        });

        $topProfitableMonth = !empty($candidates) ? $candidates[0] : null;

        // Último acceso registrado y recientes para carga instantánea SSR
        $latestLog = $em->createQuery('
            SELECT a, c FROM App\\Entity\\AccessLog a
            JOIN a.Client c
            ORDER BY a.id DESC
        ')
        ->setMaxResults(1)
        ->getOneOrNullResult();

        $recentLogs = $em->createQuery('
            SELECT a, c FROM App\\Entity\\AccessLog a
            JOIN a.Client c
            ORDER BY a.id DESC
        ')
        ->setMaxResults(5)
        ->getResult();

        $initialClient = null;
        if ($latestLog) {
            $client = $latestLog->getClient();
            $sub = $client->getSubscriptions()->last() ?: null;
            $isActive = $sub && $sub->isActive();
            $endDate = $sub?->getEndDate();
            $daysRemaining = null;
            if ($endDate) {
                $today = new \DateTime('today');
                $endDay = (clone $endDate)->setTime(0, 0, 0);
                $daysRemaining = (int) $today->diff($endDay)->format('%r%a');
            }

            $initialClient = [
                'accessId'            => $latestLog->getId(),
                'status'              => $latestLog->getStatus() ?? 'success',
                'accessTime'          => $latestLog->getAccessTime()?->format('c'),
                'accessTimeFormatted' => $latestLog->getAccessTime()?->format('H:i:s'),
                'id'                  => $client->getId(),
                'clientNumber'        => $client->getEffectiveClientNumber(),
                'name'                => $client->getName(),
                'surname'             => $client->getSurname(),
                'cardCode'            => $client->getCardCode(),
                'hasPaid'             => $isActive,
                'subType'             => $sub?->getSubscriptionType()?->getName() ?? 'Sin cuota',
                'endDate'             => $endDate?->format('d/m/Y'),
                'daysRemaining'       => $daysRemaining,
                'isDailyPass'         => $sub?->getSubscriptionType()?->isDailyPass() ?? false,
                'remainingPasses'     => $sub?->getRemainingPasses(),
                'photoUrl'            => $client->getPhoto() ? '/uploads/clients/' . $client->getPhoto() : null,
                'welcomeMessage'      => $client->getWelcomeMessage(),
            ];
        }

        $initialRecent = [];
        foreach ($recentLogs as $log) {
            $c = $log->getClient();
            $s = $c->getSubscriptions()->last() ?: null;
            $initialRecent[] = [
                'accessId'   => $log->getId(),
                'status'     => $log->getStatus() ?? 'success',
                'accessTime' => $log->getAccessTime()?->format('H:i:s'),
                'clientId'   => $c->getId(),
                'clientNumber' => $c->getEffectiveClientNumber(),
                'name'       => $c->getName() . ' ' . $c->getSurname(),
                'hasPaid'    => $s && $s->isActive(),
                'photoUrl'   => $c->getPhoto() ? '/uploads/clients/' . $c->getPhoto() : null,
            ];
        }

        // Aforo estimado en los últimos 90 minutos
        $ninetyMinutesAgo = (new \DateTime())->modify('-90 minutes');
        $currentOccupancy = (int) $conn->fetchOne("
            SELECT COUNT(DISTINCT client_id) 
            FROM access_log 
            WHERE access_time >= :since AND status = 'success'
        ", ['since' => $ninetyMinutesAgo->format('Y-m-d H:i:s')]);

        // Suscripciones que vencen en los próximos 3 días (Aviso preventivo WhatsApp)
        $expiringIn3Days = $em->getRepository(Subscription::class)->findExpiringSoon(3);
        $expiringList = [];
        $today = new \DateTime('today');
        foreach ($expiringIn3Days as $sub) {
            $client = $sub->getClient();
            if (!$client) continue;
            $endDate = $sub->getEndDate();
            $daysRemaining = 0;
            if ($endDate) {
                $until = (clone $endDate)->setTime(0, 0, 0);
                $daysRemaining = (int) $today->diff($until)->format('%r%a');
            }
            $cleanPhone = preg_replace('/[^0-9]/', '', (string)$client->getPhone());
            $expiringList[] = [
                'clientId'      => $client->getId(),
                'clientNumber'  => $client->getEffectiveClientNumber(),
                'name'          => $client->getName() . ' ' . $client->getSurname(),
                'firstName'     => $client->getName(),
                'phone'         => $client->getPhone(),
                'cleanPhone'    => $cleanPhone,
                'subType'       => $sub->getSubscriptionType()?->getName() ?? 'Suscripción',
                'endDate'       => $endDate?->format('d/m/Y'),
                'daysRemaining' => $daysRemaining,
            ];
        }

        // Cumpleaños de hoy
        $birthdayRows = $conn->fetchAllAssociative("
            SELECT id, name, surname, phone, birth_date
            FROM client
            WHERE birth_date IS NOT NULL 
              AND MONTH(birth_date) = :m 
              AND DAY(birth_date) = :d
        ", ['m' => (int)$now->format('m'), 'd' => (int)$now->format('d')]);

        $birthdayList = [];
        foreach ($birthdayRows as $bRow) {
            $bName = (string) EncryptionService::decryptValue($bRow['name']);
            $bSurname = (string) EncryptionService::decryptValue($bRow['surname']);
            $bPhone = (string) EncryptionService::decryptValue($bRow['phone']);
            $cleanPhone = preg_replace('/[^0-9]/', '', $bPhone);
            $birthDate = new \DateTime($bRow['birth_date']);
            $age = $now->diff($birthDate)->y;

            $birthdayList[] = [
                'id'         => (int)$bRow['id'],
                'name'       => $bName . ' ' . $bSurname,
                'firstName'  => $bName,
                'phone'      => $bPhone,
                'cleanPhone' => $cleanPhone,
                'age'        => $age,
            ];
        }

        // Socios en riesgo de abandono / ausentes (>15 días sin registrar acceso con cuota activa)
        $fifteenDaysAgo = (new \DateTime())->modify('-15 days');
        $inactiveSubs = $em->getRepository(Subscription::class)->createQueryBuilder('s')
            ->join('s.client', 'c')
            ->where('s.isActive = true')
            ->andWhere('s.endDate >= :today')
            ->andWhere('(c.lastAccess < :fifteenDaysAgo OR c.lastAccess IS NULL)')
            ->setParameter('today', $today)
            ->setParameter('fifteenDaysAgo', $fifteenDaysAgo)
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();

        $inactiveList = [];
        foreach ($inactiveSubs as $sub) {
            $client = $sub->getClient();
            if (!$client) continue;
            $lastAccess = $client->getLastAccess();
            $daysSince = $lastAccess ? (int) $today->diff((clone $lastAccess)->setTime(0, 0, 0))->format('%a') : null;
            $cleanPhone = preg_replace('/[^0-9]/', '', (string)$client->getPhone());
            $inactiveList[] = [
                'clientId'     => $client->getId(),
                'clientNumber' => $client->getEffectiveClientNumber(),
                'name'         => $client->getName() . ' ' . $client->getSurname(),
                'firstName'    => $client->getName(),
                'phone'        => $client->getPhone(),
                'cleanPhone'   => $cleanPhone,
                'lastAccess'   => $lastAccess ? $lastAccess->format('d/m/Y') : 'Nunca',
                'daysSince'    => $daysSince,
                'subType'      => $sub->getSubscriptionType()?->getName() ?? 'Cuota',
            ];
        }

        return $this->render('dashboard.html.twig', [
            'initialClient'      => $initialClient,
            'initialRecent'      => $initialRecent,
            'latestAccessId'     => $latestLog?->getId() ?? 0,
            'topProfitableMonth' => $topProfitableMonth,
            'currentMonthName'   => $currentMonthName,
            'currentOccupancy'   => $currentOccupancy,
            'expiringIn3Days'    => $expiringList,
            'birthdayClients'    => $birthdayList,
            'inactiveClients'    => $inactiveList,
        ]);
    }

    #[Route('/api/turnstile-clients', name: 'app_api_turnstile_clients', methods: ['GET'])]
    public function apiTurnstileClients(
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response
    {
        $latestLog = $em->createQuery('
            SELECT a, c FROM App\\Entity\\AccessLog a
            JOIN a.Client c
            ORDER BY a.id DESC
        ')
        ->setMaxResults(1)
        ->getOneOrNullResult();

        $recentLogs = $em->createQuery('
            SELECT a, c FROM App\\Entity\\AccessLog a
            JOIN a.Client c
            ORDER BY a.id DESC
        ')
        ->setMaxResults(5)
        ->getResult();

        $latestClientData = null;
        if ($latestLog) {
            $client = $latestLog->getClient();
            $sub = $client->getSubscriptions()->last() ?: null;
            $isActive = $sub && $sub->isActive();
            $endDate = $sub?->getEndDate();
            $daysRemaining = null;
            if ($endDate) {
                $today = new \DateTime('today');
                $endDay = (clone $endDate)->setTime(0, 0, 0);
                $daysRemaining = (int) $today->diff($endDay)->format('%r%a');
            }

            $latestClientData = [
                'accessId'            => $latestLog->getId(),
                'status'              => $latestLog->getStatus() ?? 'success',
                'accessTime'          => $latestLog->getAccessTime()?->format('c'),
                'accessTimeFormatted' => $latestLog->getAccessTime()?->format('H:i:s'),
                'id'                  => $client->getId(),
                'clientNumber'        => $client->getEffectiveClientNumber(),
                'name'                => $client->getName(),
                'surname'             => $client->getSurname(),
                'cardCode'            => $client->getCardCode(),
                'hasPaid'             => $isActive,
                'subType'             => $sub?->getSubscriptionType()?->getName() ?? 'Sin cuota',
                'endDate'             => $endDate?->format('d/m/Y'),
                'daysRemaining'       => $daysRemaining,
                'isDailyPass'         => $sub?->getSubscriptionType()?->isDailyPass() ?? false,
                'remainingPasses'     => $sub?->getRemainingPasses(),
                'photoUrl'            => $client->getPhoto() ? '/uploads/clients/' . $client->getPhoto() : null,
                'welcomeMessage'      => $client->getWelcomeMessage(),
                'csrfToken'           => $csrfTokenManager->getToken('renew' . $client->getId())->getValue(),
            ];
        }

        $recentList = [];
        foreach ($recentLogs as $log) {
            $c = $log->getClient();
            $s = $c->getSubscriptions()->last() ?: null;
            $recentList[] = [
                'accessId'   => $log->getId(),
                'status'     => $log->getStatus() ?? 'success',
                'accessTime' => $log->getAccessTime()?->format('H:i:s'),
                'clientId'   => $c->getId(),
                'clientNumber' => $c->getEffectiveClientNumber(),
                'name'       => $c->getName() . ' ' . $c->getSurname(),
                'hasPaid'    => $s && $s->isActive(),
                'photoUrl'   => $c->getPhoto() ? '/uploads/clients/' . $c->getPhoto() : null,
            ];
        }

        return $this->json([
            'latestAccessId' => $latestLog?->getId() ?? 0,
            'client'         => $latestClientData,
            'recent'         => $recentList,
        ]);
    }

    /** Estadísticas para el dashboard: accesos, distribución horaria, suscripciones próximas a vencer */
    #[Route('/api/stats', name: 'app_api_stats', methods: ['GET'])]
    public function apiStats(
        AccessLogRepository   $accessLogRepo,
        SubscriptionRepository $subscriptionRepo,
        ClientRepository       $clientRepo,
        EntityManagerInterface $em
    ): Response {
        $from30Days = (new \DateTime())->modify('-30 days');

        $accessesByDay = $accessLogRepo->countAccessesByDay($from30Days);
        $byHour        = $accessLogRepo->countByHour();
        $byStatus      = $accessLogRepo->countByStatus();
        $todayCount    = $accessLogRepo->countToday();
        $expiring      = $subscriptionRepo->findExpiringSoon(7);
        $totalActiveClients = $subscriptionRepo->count(['isActive' => true]);
        $totalClients  = $clientRepo->count([]);

        $conn = $em->getConnection();
        $ninetyMinutesAgo = (new \DateTime())->modify('-90 minutes');
        $currentOccupancy = (int) $conn->fetchOne("
            SELECT COUNT(DISTINCT client_id) 
            FROM access_log 
            WHERE access_time >= :since AND status = 'success'
        ", ['since' => $ninetyMinutesAgo->format('Y-m-d H:i:s')]);

        $expiringData = [];
        foreach ($expiring as $sub) {
            $expiringData[] = [
                'clientId'   => $sub->getClient()->getId(),
                'clientName' => $sub->getClient()->getName() . ' ' . $sub->getClient()->getSurname(),
                'endDate'    => $sub->getEndDate()?->format('d/m/Y'),
                'type'       => $sub->getSubscriptionType()->getName(),
            ];
        }

        return $this->json([
            'accessesByDay'      => $accessesByDay,
            'byHour'             => $byHour,
            'byStatus'           => $byStatus,
            'todayCount'         => $todayCount,
            'currentOccupancy'   => $currentOccupancy,
            'expiring'           => $expiringData,
            'totalActiveClients' => $totalActiveClients,
            'totalClients'       => $totalClients,
        ]);
    }
}

