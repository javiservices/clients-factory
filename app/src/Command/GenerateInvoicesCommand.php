<?php

namespace App\Command;

use App\Entity\Client;
use App\Repository\ClientRepository;
use App\Repository\UserRepository;
use App\Service\InvoiceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-invoices',
    description: 'Genera facturas electrónicas para clientes facturables en un periodo determinado',
)]
class GenerateInvoicesCommand extends Command
{
    private ClientRepository $clientRepository;
    private InvoiceService $invoiceService;
    private UserRepository $userRepository;

    public function __construct(ClientRepository $clientRepository, InvoiceService $invoiceService, UserRepository $userRepository)
    {
        $this->clientRepository = $clientRepository;
        $this->invoiceService = $invoiceService;
        $this->userRepository = $userRepository;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('start-date', InputArgument::REQUIRED, 'Fecha de inicio del periodo (YYYY-MM-DD)')
            ->addArgument('end-date', InputArgument::REQUIRED, 'Fecha de fin del periodo (YYYY-MM-DD)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Ejecutar en modo simulación sin crear facturas')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $startDate = new \DateTime($input->getArgument('start-date'));
        $endDate = new \DateTime($input->getArgument('end-date'));
        $dryRun = $input->getOption('dry-run');

        // Obtener el primer usuario (asumiendo que es el administrador)
        $user = $this->userRepository->findOneBy([]);
        if (!$user) {
            $io->error('No se encontró ningún usuario en el sistema');
            return Command::FAILURE;
        }

        if (!$user->getCompanyName() || !$user->getTaxId()) {
            $io->error('El usuario no tiene configurada la información de la empresa. Configure el perfil primero.');
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->note('Ejecutando en modo simulación. No se crearán facturas.');
        }

        $clients = $this->clientRepository->findBy(['billable' => true]);

        $generatedCount = 0;
        $errorCount = 0;

        foreach ($clients as $client) {
            try {
                $invoice = $this->invoiceService->generateInvoiceForClient($client, $user, $startDate, $endDate);
                if ($invoice) {
                    $generatedCount++;
                    $io->success("Factura generada: {$invoice->getInvoiceNumber()} para {$client->getName()} {$client->getSurname()}");
                } else {
                    $io->warning("No se pudo generar factura para {$client->getName()} {$client->getSurname()} (sin registros de facturación)");
                }
            } catch (\Exception $e) {
                $errorCount++;
                $io->error("Error generando factura para {$client->getName()} {$client->getSurname()}: {$e->getMessage()}");
            }
        }

        $io->info("Proceso completado. Facturas generadas: {$generatedCount}, Errores: {$errorCount}");

        return Command::SUCCESS;
    }
}
