<?php

namespace App\Controller;

use App\Entity\Client;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/statistics')]
final class StatisticsController extends AbstractController
{
    #[Route('', name: 'app_statistics_index', methods: ['GET'])]
    public function index(Request $request, ClientRepository $clientRepository, EntityManagerInterface $em): Response
    {
        $scope = $request->query->get('scope', 'all'); // 'all' (histórico) o 'active' (socios activos con cuota)
        $minPaid = (float) $request->query->get('min_paid', 50.0);

        // 1. Obtener el ranking de "Socios Fantasma / Más Rentables" (Pagan cuota y menos asisten)
        $qbProfitable = $clientRepository->createQueryBuilder('c');
        $qbProfitable->where('c.totalPaid >= :minPaid')
            ->setParameter('minPaid', $minPaid)
            ->orderBy('c.totalAccesses', 'ASC')
            ->addOrderBy('c.totalPaid', 'DESC')
            ->setMaxResults(20);

        if ($scope === 'active') {
            $qbProfitable->innerJoin('c.subscriptions', 's')
                ->andWhere('s.isActive = true');
        }

        /** @var Client[] $profitableClients */
        $profitableClients = $qbProfitable->getQuery()->getResult();

        // El campeón absoluto (#1)
        $topProfitable = !empty($profitableClients) ? $profitableClients[0] : null;

        // 2. Obtener el ranking de "Socios Más Fieles / Asiduos" (Los que más asisten al gimnasio)
        $qbFrequent = $clientRepository->createQueryBuilder('c');
        $qbFrequent->where('c.totalAccesses > 0')
            ->orderBy('c.totalAccesses', 'DESC')
            ->setMaxResults(10);

        /** @var Client[] $frequentClients */
        $frequentClients = $qbFrequent->getQuery()->getResult();

        // 3. Métricas globales del gimnasio
        $conn = $em->getConnection();
        $globalStats = $conn->fetchAssociative("
            SELECT 
                COUNT(*) as total_members,
                SUM(total_accesses) as total_gym_accesses,
                SUM(total_paid) as total_gym_revenue,
                SUM(CASE WHEN total_accesses = 0 AND total_paid > 0 THEN 1 ELSE 0 END) as zero_access_paying,
                AVG(CASE WHEN total_accesses > 0 THEN total_accesses ELSE NULL END) as avg_accesses_active
            FROM client
        ") ?: [];

        return $this->render('statistics/index.html.twig', [
            'scope'             => $scope,
            'minPaid'           => $minPaid,
            'profitableClients' => $profitableClients,
            'topProfitable'     => $topProfitable,
            'frequentClients'   => $frequentClients,
            'globalStats'       => $globalStats,
        ]);
    }

    #[Route('/diploma/{id}', name: 'app_statistics_diploma', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function diploma(Client $client): Response
    {
        $costPerVisit = $client->getCostPerVisit();
        $totalPaid = $client->getTotalPaid();
        $totalAccesses = $client->getTotalAccesses();

        // Título honorífico según su nivel de rentabilidad
        if ($totalAccesses === 0 && $totalPaid > 0) {
            $title = 'Socio Donante Platino (Socio Fantasma)';
            $badgeColor = '#D4AF37';
            $verdict = '100% de rentabilidad. Cero huella de sudor, cero desgaste de material. El socio que todo dueño de gimnasio sueña.';
        } elseif ($costPerVisit >= 50) {
            $title = 'Socio Mecenas de Oro';
            $badgeColor = '#F59E0B';
            $verdict = 'Cada una de sus escasas visitas cuesta más que un pase VIP a un festival. Altamente apreciado por la gerencia.';
        } elseif ($costPerVisit >= 20) {
            $title = 'Socio Patrocinador Plata';
            $badgeColor = '#3B82F6';
            $verdict = 'Viene de vez en cuando a recordar cómo huele el magnesio, asegurando una rentabilidad ejemplar.';
        } else {
            $title = 'Socio Ejemplar';
            $badgeColor = '#10B981';
            $verdict = 'Aprovecha sus instalaciones pero su compromiso económico es digno de reconocimiento.';
        }

        return $this->render('statistics/diploma.html.twig', [
            'client'        => $client,
            'costPerVisit'  => $costPerVisit,
            'totalPaid'     => $totalPaid,
            'totalAccesses' => $totalAccesses,
            'title'         => $title,
            'badgeColor'    => $badgeColor,
            'verdict'       => $verdict,
            'today'         => new \DateTime(),
        ]);
    }
}
