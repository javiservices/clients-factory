<?php

namespace App\Controller;

use App\Entity\Subscription;
use App\Repository\AccessLogRepository;
use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use App\Repository\SubscriptionRepository;
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

        return $this->render('dashboard.html.twig', [
            'turnstileClients'   => [],
            'topProfitableMonth' => $topProfitableMonth,
            'currentMonthName'   => $currentMonthName,
        ]);
    }

    #[Route('/api/turnstile-clients', name: 'app_api_turnstile_clients', methods: ['GET'])]
    public function apiTurnstileClients(
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response
    {
        $since  = new \DateTime('-10 minutes');
        $client = $em->createQuery('
            SELECT c FROM App\\Entity\\Client c
            WHERE c.lastAccess >= :since
            ORDER BY c.lastAccess DESC
        ')
        ->setParameter('since', $since)
        ->setMaxResults(1)
        ->getOneOrNullResult();

        $data = [];
        if ($client) {
            $data[] = [
                'id'             => $client->getId(),
                'name'           => $client->getName(),
                'surname'        => $client->getSurname(),
                'cardCode'       => $client->getCardCode(),
                'lastAccess'     => $client->getLastAccess()?->format('c'),
                'hasPaid'        => $client->getSubscriptions()->last() && $client->getSubscriptions()->last()->isActive(),
                'photoUrl'       => $client->getPhoto() ? '/uploads/clients/' . $client->getPhoto() : null,
                'welcomeMessage' => $client->getWelcomeMessage(),
                'csrfToken'      => $csrfTokenManager->getToken('renew' . $client->getId())->getValue(),
            ];
        }

        return $this->json($data);
    }

    /** Estadísticas para el dashboard: accesos, distribución horaria, suscripciones próximas a vencer */
    #[Route('/api/stats', name: 'app_api_stats', methods: ['GET'])]
    public function apiStats(
        AccessLogRepository   $accessLogRepo,
        SubscriptionRepository $subscriptionRepo,
        ClientRepository       $clientRepo
    ): Response {
        $from30Days = (new \DateTime())->modify('-30 days');

        $accessesByDay = $accessLogRepo->countAccessesByDay($from30Days);
        $byHour        = $accessLogRepo->countByHour();
        $byStatus      = $accessLogRepo->countByStatus();
        $todayCount    = $accessLogRepo->countToday();
        $expiring      = $subscriptionRepo->findExpiringSoon(7);
        $totalClients  = $clientRepo->count([]);

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
            'accessesByDay' => $accessesByDay,
            'byHour'        => $byHour,
            'byStatus'      => $byStatus,
            'todayCount'    => $todayCount,
            'expiring'      => $expiringData,
            'totalClients'  => $totalClients,
        ]);
    }
}

