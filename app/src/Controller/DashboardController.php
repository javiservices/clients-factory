<?php

namespace App\Controller;

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
    public function dashboard(): Response
    {
        return $this->render('dashboard.html.twig', [
            'turnstileClients' => [],
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
                'id'         => $client->getId(),
                'name'       => $client->getName(),
                'surname'    => $client->getSurname(),
                'cardCode'   => $client->getCardCode(),
                'lastAccess' => $client->getLastAccess()?->format('c'),
                'hasPaid'    => $client->getSubscriptions()->last() && $client->getSubscriptions()->last()->isActive(),
                'photoUrl'   => $client->getPhoto() ? '/uploads/clients/' . $client->getPhoto() : null,
                'csrfToken'  => $csrfTokenManager->getToken('renew' . $client->getId())->getValue(),
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

