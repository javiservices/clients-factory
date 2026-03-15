<?php
namespace App\Command;

use App\Entity\ProcessedLog;
use App\Repository\AccessLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessAccessLogsCommand extends Command
{
    // Definir el nombre del comando como propiedad protegida
    protected static $defaultName = 'app:process-access-logs';
    protected static $defaultDescription = 'Procesa los logs de acceso y los guarda en la base de datos como JSON';

    private $entityManager;
    private $accessLogRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        AccessLogRepository $accessLogRepository
    ) {
        // Importante: llamar al constructor padre ANTES de añadir tus propias inicializaciones
        parent::__construct();
        
        $this->entityManager = $entityManager;
        $this->accessLogRepository = $accessLogRepository;
    }

    protected function configure(): void
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription(self::$defaultDescription)
            ->setHelp('Este comando procesa los logs de acceso no procesados y los convierte en registros JSON consolidados por día.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $date = new \DateTime();
        $dateString = $date->format('Y-m-d');

        // Obtener todos los logs no procesados
        $logs = $this->accessLogRepository->findBy(['processed' => false]);

        if (empty($logs)) {
            $output->writeln('No hay logs para procesar');
            return Command::SUCCESS;
        }

        $logsData = [];
        foreach ($logs as $log) {
            $logsData[] = [
                'client_id' => $log->getClient()->getId(),
                'client_name' => $log->getClient()->getName(),
                'access_time' => $log->getAccessTime()->format('Y-m-d H:i:s'),
                'status' => $log->getStatus(),
            ];

            // Actualizar lastAccess del cliente si el acceso fue exitoso
            if ($log->getStatus() === 'success') {
                $log->getClient()->setLastAccess($log->getAccessTime());
            }

            // Marcar como procesado
            $log->setProcessed(true);
            $this->entityManager->persist($log);
        }

        // Buscar si ya existe un registro para esta fecha
        $processedLog = $this->entityManager->getRepository(ProcessedLog::class)
            ->findOneBy(['date' => new \DateTime($dateString)]);

        if (!$processedLog) {
            $processedLog = new ProcessedLog();
            $processedLog->setDate(new \DateTime($dateString));
        }

        // Combinar logs existentes (si los hay) con los nuevos
        $existingLogs = $processedLog->getLogs();
        $allLogs = array_merge($existingLogs, $logsData);
        $processedLog->setLogs($allLogs);

        $this->entityManager->persist($processedLog);
        $this->entityManager->flush();

        $output->writeln(sprintf('Se han procesado %d registros y guardado en la tabla ProcessedLog', count($logs)));
        return Command::SUCCESS;
    }
}