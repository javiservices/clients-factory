<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Subscription;
use App\Repository\AccessLogRepository;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/statistics')]
final class StatisticsController extends AbstractController
{
    private const MONTH_NAMES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];

    #[Route('', name: 'app_statistics_index', methods: ['GET'])]
    public function index(Request $request, ClientRepository $clientRepository, EntityManagerInterface $em): Response
    {
        $now = new \DateTime();
        $preset = $request->query->get('preset', 'this_month');

        // Determinar rango de fechas y etiqueta del período
        if ($preset === 'all_time') {
            $startDate = null;
            $endDate = null;
            $startDateStr = '';
            $endDateStr = '';
            $periodLabel = 'Histórico Completo (Todos los tiempos)';
        } elseif ($preset === 'last_month') {
            $startDate = new \DateTime('first day of last month 00:00:00');
            $endDate = new \DateTime('last day of last month 23:59:59');
            $startDateStr = $startDate->format('Y-m-d');
            $endDateStr = $endDate->format('Y-m-d');
            $periodLabel = self::MONTH_NAMES[(int)$startDate->format('n')] . ' ' . $startDate->format('Y');
        } elseif ($preset === 'this_year') {
            $startDate = new \DateTime($now->format('Y') . '-01-01 00:00:00');
            $endDate = new \DateTime($now->format('Y') . '-12-31 23:59:59');
            $startDateStr = $startDate->format('Y-m-d');
            $endDateStr = $endDate->format('Y-m-d');
            $periodLabel = 'Año ' . $now->format('Y');
        } elseif ($preset === 'custom' && $request->query->has('start_date') && $request->query->has('end_date')) {
            try {
                $startDate = new \DateTime($request->query->get('start_date') . ' 00:00:00');
            } catch (\Exception) {
                $startDate = new \DateTime('first day of this month 00:00:00');
            }
            try {
                $endDate = new \DateTime($request->query->get('end_date') . ' 23:59:59');
            } catch (\Exception) {
                $endDate = new \DateTime('last day of this month 23:59:59');
            }
            $startDateStr = $startDate->format('Y-m-d');
            $endDateStr = $endDate->format('Y-m-d');
            $periodLabel = $startDate->format('d/m/Y') . ' al ' . $endDate->format('d/m/Y');
        } else {
            // Predeterminado: Mes actual
            $preset = 'this_month';
            $startDate = new \DateTime('first day of this month 00:00:00');
            $endDate = new \DateTime('last day of this month 23:59:59');
            $startDateStr = $startDate->format('Y-m-d');
            $endDateStr = $endDate->format('Y-m-d');
            $periodLabel = self::MONTH_NAMES[(int)$now->format('n')] . ' ' . $now->format('Y');
        }

        $conn = $em->getConnection();

        if ($preset === 'all_time') {
            // ─── MODO HISTÓRICO COMPLETO ───
            $qbProfitable = $clientRepository->createQueryBuilder('c')
                ->where('c.totalPaid >= 50.0')
                ->orderBy('c.totalAccesses', 'ASC')
                ->addOrderBy('c.totalPaid', 'DESC')
                ->setMaxResults(20);

            $profitableClientsRaw = $qbProfitable->getQuery()->getResult();
            $profitableClients = [];
            foreach ($profitableClientsRaw as $c) {
                $profitableClients[] = [
                    'client'           => $c,
                    'paidInPeriod'     => $c->getTotalPaid(),
                    'accessesInPeriod' => $c->getTotalAccesses(),
                    'costPerVisit'     => $c->getCostPerVisit(),
                ];
            }

            $qbFrequent = $clientRepository->createQueryBuilder('c')
                ->where('c.totalAccesses > 0')
                ->orderBy('c.totalAccesses', 'DESC')
                ->setMaxResults(10);

            $frequentClientsRaw = $qbFrequent->getQuery()->getResult();
            $frequentClients = [];
            foreach ($frequentClientsRaw as $c) {
                $frequentClients[] = [
                    'client'           => $c,
                    'accessesInPeriod' => $c->getTotalAccesses(),
                    'costPerVisit'     => $c->getCostPerVisit(),
                ];
            }

            $globalStats = $conn->fetchAssociative("
                SELECT 
                    COUNT(*) as total_members,
                    SUM(total_accesses) as total_gym_accesses,
                    SUM(total_paid) as total_gym_revenue,
                    SUM(CASE WHEN total_accesses = 0 AND total_paid > 0 THEN 1 ELSE 0 END) as zero_access_paying,
                    AVG(CASE WHEN total_accesses > 0 THEN total_accesses ELSE NULL END) as avg_accesses_active
                FROM client
            ") ?: [];
        } else {
            // ─── MODO PERÍODO (MES ACTUAL O RANGO ESPECÍFICO) ───

            // 1. Mapa de accesos en el período desde access_log
            $accessRows = $conn->fetchAllAssociative("
                SELECT client_id, COUNT(*) as cnt 
                FROM access_log 
                WHERE access_time >= :start AND access_time <= :end AND status = 'success'
                GROUP BY client_id
            ", [
                'start' => $startDate->format('Y-m-d H:i:s'),
                'end'   => $endDate->format('Y-m-d H:i:s'),
            ]);

            $accessMap = [];
            $totalPeriodAccesses = 0;
            foreach ($accessRows as $row) {
                $cid = (int) $row['client_id'];
                $cnt = (int) $row['cnt'];
                $accessMap[$cid] = $cnt;
                $totalPeriodAccesses += $cnt;
            }

            // 2. Socios con suscripción activa / pagada en el período
            /** @var Subscription[] $subscriptions */
            $subscriptions = $em->getRepository(Subscription::class)->createQueryBuilder('s')
                ->join('s.client', 'c')
                ->leftJoin('s.subscriptionType', 'st')
                ->where('s.isActive = true')
                ->andWhere('s.lastPaymentDate IS NOT NULL')
                ->andWhere('s.lastPaymentDate <= :end')
                ->andWhere('s.endDate >= :start')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate)
                ->getQuery()
                ->getResult();

            $processedClients = [];
            $profitableClients = [];
            $totalPeriodRevenue = 0.0;
            $zeroAccessCount = 0;

            foreach ($subscriptions as $sub) {
                $client = $sub->getClient();
                if (!$client || isset($processedClients[$client->getId()])) {
                    continue;
                }
                $processedClients[$client->getId()] = true;

                $price = $sub->getSubscriptionType()?->getPrice() ?? 50.0;
                $accesses = $accessMap[$client->getId()] ?? 0;
                $costPerVisit = $accesses > 0 ? round($price / $accesses, 2) : $price;

                $totalPeriodRevenue += $price;
                if ($accesses === 0) {
                    $zeroAccessCount++;
                }

                $profitableClients[] = [
                    'client'              => $client,
                    'paidInPeriod'        => $price,
                    'accessesInPeriod'    => $accesses,
                    'costPerVisit'        => $costPerVisit,
                    'totalHistoricalPaid' => $client->getTotalPaid(),
                ];
            }

            // Ordenar: menos accesos en el período primero; desempate por mayor aportación histórica
            usort($profitableClients, function (array $a, array $b) {
                if ($a['accessesInPeriod'] !== $b['accessesInPeriod']) {
                    return $a['accessesInPeriod'] <=> $b['accessesInPeriod'];
                }
                if ($a['totalHistoricalPaid'] !== $b['totalHistoricalPaid']) {
                    return $b['totalHistoricalPaid'] <=> $a['totalHistoricalPaid'];
                }
                return $a['client']->getId() <=> $b['client']->getId();
            });

            // Limitar a los 20 más rentables
            $profitableClients = array_slice($profitableClients, 0, 20);

            // 3. Socios más asiduos en el período
            arsort($accessMap);
            $frequentClients = [];
            $clientRepo = $em->getRepository(Client::class);
            $count = 0;
            foreach ($accessMap as $cid => $accCount) {
                if ($count >= 10) {
                    break;
                }
                $c = $clientRepo->find($cid);
                if ($c) {
                    $frequentClients[] = [
                        'client'           => $c,
                        'accessesInPeriod' => $accCount,
                        'costPerVisit'     => round(50.0 / max(1, $accCount), 2),
                    ];
                    $count++;
                }
            }

            $activeCount = count($processedClients);
            $globalStats = [
                'total_members'       => $activeCount,
                'total_gym_accesses'  => $totalPeriodAccesses,
                'total_gym_revenue'   => $totalPeriodRevenue,
                'zero_access_paying'  => $zeroAccessCount,
                'avg_accesses_active' => $activeCount > 0 ? ($totalPeriodAccesses / $activeCount) : 0,
            ];
        }

        $topProfitable = !empty($profitableClients) ? $profitableClients[0] : null;

        return $this->render('statistics/index.html.twig', [
            'preset'            => $preset,
            'startDate'         => $startDateStr,
            'endDate'           => $endDateStr,
            'periodLabel'       => $periodLabel,
            'profitableClients' => $profitableClients,
            'topProfitable'     => $topProfitable,
            'frequentClients'   => $frequentClients,
            'globalStats'       => $globalStats,
        ]);
    }

    #[Route('/diploma/{id}', name: 'app_statistics_diploma', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function diploma(Client $client, Request $request, EntityManagerInterface $em): Response
    {
        $now = new \DateTime();
        $preset = $request->query->get('preset', 'this_month');

        if ($preset === 'all_time') {
            $periodLabel = 'Todos los Tiempos (Histórico)';
            $periodPaid = $client->getTotalPaid();
            $periodAccesses = $client->getTotalAccesses();
            $costPerVisit = $client->getCostPerVisit();
        } else {
            // Periodo por fechas (o mes actual por defecto)
            $startDateStr = $request->query->get('start_date');
            $endDateStr = $request->query->get('end_date');

            if ($startDateStr && $endDateStr) {
                try {
                    $startDate = new \DateTime($startDateStr . ' 00:00:00');
                    $endDate = new \DateTime($endDateStr . ' 23:59:59');
                } catch (\Exception) {
                    $startDate = new \DateTime('first day of this month 00:00:00');
                    $endDate = new \DateTime('last day of this month 23:59:59');
                }
            } else {
                $startDate = new \DateTime('first day of this month 00:00:00');
                $endDate = new \DateTime('last day of this month 23:59:59');
            }

            // Etiqueta del período
            if ($startDate->format('Y-m-01') === $startDate->format('Y-m-d') && $endDate->format('Y-m-t') === $endDate->format('Y-m-d')) {
                $periodLabel = self::MONTH_NAMES[(int)$startDate->format('n')] . ' de ' . $startDate->format('Y');
            } else {
                $periodLabel = $startDate->format('d/m/Y') . ' al ' . $endDate->format('d/m/Y');
            }

            // Conteo de accesos del cliente en el período desde access_log
            $conn = $em->getConnection();
            $periodAccesses = (int) $conn->fetchOne("
                SELECT COUNT(*) 
                FROM access_log 
                WHERE client_id = :cid AND access_time >= :start AND access_time <= :end AND status = 'success'
            ", [
                'cid'   => $client->getId(),
                'start' => $startDate->format('Y-m-d H:i:s'),
                'end'   => $endDate->format('Y-m-d H:i:s'),
            ]);

            // Cuota del período
            $lastSub = $client->getSubscriptions()->last();
            $periodPaid = $lastSub?->getSubscriptionType()?->getPrice() ?? 50.0;
            $costPerVisit = $periodAccesses > 0 ? round($periodPaid / $periodAccesses, 2) : $periodPaid;
        }

        // Título honorífico
        if ($periodAccesses === 0) {
            $honorTitle = 'Socio Donante Platino (Socio Fantasma)';
            $verdict = '100% de rentabilidad. Cero huella de sudor, cero desgaste de material. El socio que todo dueño de gimnasio sueña.';
        } elseif ($costPerVisit >= 50) {
            $honorTitle = 'Socio Mecenas de Oro';
            $verdict = 'Cada una de sus escasas visitas cuesta más que un pase VIP a un festival. Altamente apreciado por la gerencia.';
        } elseif ($costPerVisit >= 20) {
            $honorTitle = 'Socio Patrocinador Plata';
            $verdict = 'Viene de vez en cuando a recordar cómo huele el magnesio, asegurando una rentabilidad ejemplar.';
        } else {
            $honorTitle = 'Socio Ejemplar';
            $verdict = 'Aprovecha sus instalaciones pero su compromiso económico es digno de reconocimiento.';
        }

        return $this->render('statistics/diploma.html.twig', [
            'client'         => $client,
            'preset'         => $preset,
            'periodLabel'    => $periodLabel,
            'periodPaid'     => $periodPaid,
            'periodAccesses' => $periodAccesses,
            'costPerVisit'   => $costPerVisit,
            'honorTitle'     => $honorTitle,
            'verdict'        => $verdict,
            'today'          => new \DateTime(),
            'startDate'      => $request->query->get('start_date'),
            'endDate'        => $request->query->get('end_date'),
        ]);
    }
}
