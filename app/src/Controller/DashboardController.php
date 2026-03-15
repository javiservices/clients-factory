<?php

namespace App\Controller;

use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        // La vista se actualizará vía AJAX, así que solo renderizamos la plantilla con array vacío
        return $this->render('dashboard.html.twig', [
            'turnstileClients' => [],
        ]);
    }

    #[Route('/api/turnstile-clients', name: 'app_api_turnstile_clients', methods: ['GET'])]
    public function apiTurnstileClients(EntityManagerInterface $em): Response
    {
        // Obtener solo el último cliente que ha pasado la tarjeta en los últimos 10 minutos
        $since = new \DateTime('-10 minutes');
        $client = $em->createQuery('
            SELECT c
            FROM App\\Entity\\Client c
            WHERE c.lastAccess >= :since
            ORDER BY c.lastAccess DESC
        ')
        ->setParameter('since', $since)
        ->setMaxResults(1)
        ->getOneOrNullResult();

        $data = [];
        if ($client) {
            $data[] = [
                'id' => $client->getId(),
                'name' => $client->getName(),
                'surname' => $client->getSurname(),
                'cardCode' => $client->getCardCode(),
                'lastAccess' => $client->getLastAccess() ? $client->getLastAccess()->format('c') : null,
                'hasPaid' => $client->getSubscriptions()->last() && $client->getSubscriptions()->last()->isActive(),
            ];
        }

        return $this->json($data);
    }
}
