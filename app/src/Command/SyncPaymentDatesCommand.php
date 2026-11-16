<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\Subscription;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:sync-payment-dates',
    description: 'Sincroniza las fechas de pago y estados reales de suscripción calculados desde el historial del Excel'
)]
class SyncPaymentDatesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $parameterBag
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('json_file', InputArgument::OPTIONAL, 'Ruta al archivo payments_sync.json', 'payments_sync.json')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simular sin persistir cambios en base de datos');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $jsonFile = $input->getArgument('json_file');
        $isDryRun = (bool) $input->getOption('dry-run');

        if (!file_exists($jsonFile)) {
            // Intentar buscar en raíz del proyecto
            $projectDir = $this->parameterBag->get('kernel.project_dir');
            $altFile = $projectDir . '/' . $jsonFile;
            if (file_exists($altFile)) {
                $jsonFile = $altFile;
            } else {
                $io->error(sprintf('No se encontró el archivo JSON: %s', $jsonFile));
                return Command::FAILURE;
            }
        }

        $io->title('Sincronización de fechas reales de pago y estados de suscripción');
        if ($isDryRun) {
            $io->note('MODO SIMULACIÓN (DRY-RUN): No se guardarán cambios en la BD.');
        }

        $data = json_decode(file_get_contents($jsonFile), true);
        if (!$data || !is_array($data)) {
            $io->error('El archivo JSON es inválido o está vacío.');
            return Command::FAILURE;
        }

        $total = count($data);
        $io->text(sprintf('Registros a procesar: %d', $total));

        $clientRepo = $this->entityManager->getRepository(Client::class);
        $subRepo = $this->entityManager->getRepository(Subscription::class);

        $progressBar = new ProgressBar($output, $total);
        $progressBar->start();

        $updatedCount = 0;
        $activeNovCount = 0;
        $pendingExpiredCount = 0;
        $neverPaidCount = 0;
        $bajaCount = 0;

        $batchSize = 100;

        foreach ($data as $i => $item) {
            $clientId = (int) $item['client_id'];
            $client = $clientRepo->find($clientId);

            if (!$client) {
                $progressBar->advance();
                continue;
            }

            /** @var Subscription|null $sub */
            $sub = $client->getSubscriptions()->last() ?: null;

            if ($sub) {
                $sub->setIsActive((bool) $item['is_active']);

                if (!empty($item['start_date'])) {
                    $sub->setStartDate(new \DateTime($item['start_date']));
                }
                if (!empty($item['end_date'])) {
                    $sub->setEndDate(new \DateTime($item['end_date']));
                } else {
                    $sub->setEndDate(null);
                }
                if (!empty($item['last_payment_date'])) {
                    $sub->setLastPaymentDate(new \DateTime($item['last_payment_date']));
                } else {
                    $sub->setLastPaymentDate(null);
                }

                $updatedCount++;

                match ($item['status']) {
                    'paid_active'     => $activeNovCount++,
                    'expired'         => $pendingExpiredCount++,
                    'never_paid'      => $neverPaidCount++,
                    'baja'            => $bajaCount++,
                    default           => null,
                };
            }

            if (!$isDryRun && (($i + 1) % $batchSize === 0)) {
                $this->entityManager->flush();
            }

            $progressBar->advance();
        }

        if (!$isDryRun) {
            $this->entityManager->flush();
        }

        $progressBar->finish();
        $io->newLine(2);

        $io->success(sprintf(
            "Proceso completado. %d suscripciones actualizadas:\n" .
            " - %d realmente pagadas en Noviembre 2026 (al corriente)\n" .
            " - %d cuotas caducadas (pagaron en el pasado pero no este mes)\n" .
            " - %d clientes que nunca han realizado un pago registrado\n" .
            " - %d clientes formalmente de baja",
            $updatedCount,
            $activeNovCount,
            $pendingExpiredCount,
            $neverPaidCount,
            $bajaCount
        ));

        return Command::SUCCESS;
    }
}
