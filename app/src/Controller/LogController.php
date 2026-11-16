<?php
namespace App\Controller;

use App\Repository\AccessLogRepository;
use App\Repository\ProcessedLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class LogController extends AbstractController
{
    private $accessLogRepository;
    private $processedLogRepository;

    public function __construct(
        AccessLogRepository $accessLogRepository, 
        ProcessedLogRepository $processedLogRepository
    ) {
        $this->accessLogRepository = $accessLogRepository;
        $this->processedLogRepository = $processedLogRepository;
    }

    #[Route('/logs', name: 'logs_view', methods: ['GET'])]
    public function viewLogs(Request $request): Response
    {
        $date = $request->query->get('date');
        if (!$date) {
            $date = (new \DateTime())->format('Y-m-d');
        }

        try {
            $dateObject = new \DateTime($date);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Formato de fecha inválido. Use YYYY-MM-DD.');
            return $this->redirectToRoute('logs_view');
        }

        $logs = $this->getLogsForDate($dateObject, $date);

        return $this->render('logs/view.html.twig', [
            'logs' => $logs,
            'date' => $date,
        ]);
    }

    #[Route('/logs/export/csv', name: 'logs_export_csv', methods: ['GET'])]
    public function exportCsv(Request $request): StreamedResponse
    {
        $date = $request->query->get('date');
        if (!$date) {
            $date = (new \DateTime())->format('Y-m-d');
        }

        try {
            $dateObject = new \DateTime($date);
        } catch (\Exception $e) {
            $dateObject = new \DateTime();
            $date = $dateObject->format('Y-m-d');
        }

        $logs = $this->getLogsForDate($dateObject, $date);
        $filename = sprintf('accesos_%s.csv', $date);

        $response = new StreamedResponse(function () use ($logs, $date) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 para apertura directa en Microsoft Excel
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['ID Cliente', 'Nombre Cliente', 'Fecha y Hora', 'Estado'], ';');

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log['client_id'] ?? '',
                    $log['client_name'] ?? '',
                    $log['access_time'] ?? '',
                    ($log['status'] ?? '') === 'success' ? 'Autorizado' : 'Denegado',
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

        return $response;
    }

    /**
     * Obtiene los logs de una fecha buscando en ProcessedLog o con fallback a AccessLog.
     */
    private function getLogsForDate(\DateTime $dateObject, string $date): array
    {
        $logs = [];
        $today = (new \DateTime())->format('Y-m-d');

        if ($date === $today) {
            $accessLogs = $this->accessLogRepository->findByDate($dateObject);
            foreach ($accessLogs as $log) {
                $logs[] = [
                    'client_id'   => $log->getClient()->getId(),
                    'client_name' => $log->getClient()->getName() . ' ' . $log->getClient()->getSurname(),
                    'access_time' => $log->getAccessTime()->format('Y-m-d H:i:s'),
                    'status'      => $log->getStatus(),
                ];
            }
        } else {
            $processedLog = $this->processedLogRepository->findOneBy(['date' => $dateObject]);
            if ($processedLog && !empty($processedLog->getLogs())) {
                $logs = $processedLog->getLogs();
            } else {
                // Fallback seguro: consultar tabla access_log si aún no fue procesado
                $accessLogs = $this->accessLogRepository->findByDate($dateObject);
                foreach ($accessLogs as $log) {
                    $logs[] = [
                        'client_id'   => $log->getClient()->getId(),
                        'client_name' => $log->getClient()->getName() . ' ' . $log->getClient()->getSurname(),
                        'access_time' => $log->getAccessTime()->format('Y-m-d H:i:s'),
                        'status'      => $log->getStatus(),
                    ];
                }
            }
        }

        return $logs;
    }
}
