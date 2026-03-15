<?php
namespace App\Controller;

use App\Repository\AccessLogRepository;
use App\Repository\ProcessedLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        // Obtener la fecha de la consulta (si no se proporciona, usar la fecha actual)
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

        $logs = [];
        $today = (new \DateTime())->format('Y-m-d');

        if ($date === $today) {
            // Si la fecha es hoy, obtener los registros de AccessLog
            $accessLogs = $this->accessLogRepository->findByDate($dateObject);
            foreach ($accessLogs as $log) {
                $logs[] = [
                    'client_id' => $log->getClient()->getId(),
                    'client_name' => $log->getClient()->getName(). " " .$log->getClient()->getSurname(),
                    'access_time' => $log->getAccessTime()->format('Y-m-d H:i:s'),
                    'status' => $log->getStatus(),
                ];
            }
        } else {
            // Si la fecha es anterior, obtener los registros de ProcessedLog
            $processedLog = $this->processedLogRepository->findOneBy(['date' => $dateObject]);
            if ($processedLog) {
                $logs = $processedLog->getLogs();
            }
        }

        return $this->render('logs/view.html.twig', [
            'logs' => $logs,
            'date' => $date,
        ]);
    }
}