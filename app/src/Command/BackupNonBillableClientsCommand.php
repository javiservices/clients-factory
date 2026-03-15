<?php

namespace App\Command;

use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'app:backup-non-billable-clients',
    description: 'Genera un backup SQL de clientes no facturables y los elimina de la base de datos',
)]
class BackupNonBillableClientsCommand extends Command
{
    private ClientRepository $clientRepository;
    private EntityManagerInterface $entityManager;
    private Filesystem $filesystem;

    public function __construct(ClientRepository $clientRepository, EntityManagerInterface $entityManager)
    {
        $this->clientRepository = $clientRepository;
        $this->entityManager = $entityManager;
        $this->filesystem = new Filesystem();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Ejecutar en modo simulación sin eliminar datos')
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'Directorio donde guardar el backup', 'var/backups')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $outputDir = $input->getOption('output-dir');

        // Crear directorio de backups si no existe
        if (!$this->filesystem->exists($outputDir)) {
            $this->filesystem->mkdir($outputDir);
        }

        // Obtener clientes no facturables
        $nonBillableClients = $this->clientRepository->findBy(['billable' => false]);

        if (empty($nonBillableClients)) {
            $io->success('No hay clientes no facturables para procesar.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Encontrados %d clientes no facturables.', count($nonBillableClients)));

        if ($dryRun) {
            $io->warning('Modo simulación activado. No se eliminarán datos.');
        }

        // Generar timestamp para el archivo
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $outputDir . '/non_billable_clients_backup_' . $timestamp . '.sql';

        // Generar dump SQL
        $sqlDump = $this->generateSqlDump($nonBillableClients);

        // Guardar el dump
        $this->filesystem->dumpFile($backupFile, $sqlDump);

        $io->success(sprintf('Backup guardado en: %s', $backupFile));

        if (!$dryRun) {
            // Eliminar los datos
            $this->deleteNonBillableClients($nonBillableClients, $io);
            $io->success('Clientes no facturables eliminados de la base de datos.');
        }

        return Command::SUCCESS;
    }

    private function generateSqlDump(array $clients): string
    {
        $sql = "-- Backup de clientes no facturables generado el " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Este archivo puede ser ejecutado para restaurar los datos\n\n";

        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($clients as $client) {
            // Insertar cliente
            $sql .= sprintf(
                "INSERT INTO client (id, name, surname, address, locality, zip_code, birth_date, phone, email, id_number, start_date, card_code, billable) VALUES (%d, '%s', '%s', %s, %s, %s, %s, %s, '%s', %s, %s, %s, %d);\n",
                $client->getId(),
                $this->escapeString($client->getName()),
                $this->escapeString($client->getSurname()),
                $client->getAddress() ? "'" . $this->escapeString($client->getAddress()) . "'" : 'NULL',
                $client->getLocality() ? "'" . $this->escapeString($client->getLocality()) . "'" : 'NULL',
                $client->getZipCode() ?? 'NULL',
                $client->getBirthDate() ? "'" . $client->getBirthDate()->format('Y-m-d') . "'" : 'NULL',
                $client->getPhone() ?? 'NULL',
                $this->escapeString($client->getEmail()),
                $client->getIdNumber() ? "'" . $this->escapeString($client->getIdNumber()) . "'" : 'NULL',
                $client->getStartDate() ? "'" . $client->getStartDate()->format('Y-m-d') . "'" : 'NULL',
                $client->getCardCode() ? "'" . $this->escapeString($client->getCardCode()) . "'" : 'NULL',
                $client->isBillable() ? 1 : 0
            );

            // Insertar suscripciones relacionadas
            foreach ($client->getSubscriptions() as $subscription) {
                $sql .= sprintf(
                    "INSERT INTO subscription (id, start_date, end_date, client_id, subscription_type_id) VALUES (%d, '%s', %s, %d, %d);\n",
                    $subscription->getId(),
                    $subscription->getStartDate()->format('Y-m-d'),
                    $subscription->getEndDate() ? "'" . $subscription->getEndDate()->format('Y-m-d') . "'" : 'NULL',
                    $client->getId(),
                    $subscription->getSubscriptionType()->getId()
                );
            }

            // Insertar logs de acceso relacionados
            foreach ($client->getAccessLogs() as $accessLog) {
                $sql .= sprintf(
                    "INSERT INTO access_log (id, access_time, client_id) VALUES (%d, '%s', %d);\n",
                    $accessLog->getId(),
                    $accessLog->getAccessTime()->format('Y-m-d H:i:s'),
                    $client->getId()
                );
            }

            // Insertar facturas relacionadas
            foreach ($client->getInvoices() as $invoice) {
                $sql .= sprintf(
                    "INSERT INTO invoice (id, issue_date, total_amount, xml_content, client_id) VALUES (%d, '%s', %.2f, %s, %d);\n",
                    $invoice->getId(),
                    $invoice->getIssueDate()->format('Y-m-d'),
                    $invoice->getTotalAmount(),
                    $invoice->getXmlContent() ? "'" . $this->escapeString($invoice->getXmlContent()) . "'" : 'NULL',
                    $client->getId()
                );
            }

            // Insertar registros de facturación relacionados
            foreach ($client->getBillingRecords() as $billingRecord) {
                $sql .= sprintf(
                    "INSERT INTO billing_record (id, amount, billing_date, description, client_id) VALUES (%d, %.2f, '%s', %s, %d);\n",
                    $billingRecord->getId(),
                    $billingRecord->getAmount(),
                    $billingRecord->getBillingDate()->format('Y-m-d'),
                    $billingRecord->getDescription() ? "'" . $this->escapeString($billingRecord->getDescription()) . "'" : 'NULL',
                    $client->getId()
                );
            }

            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        return $sql;
    }

    private function deleteNonBillableClients(array $clients, SymfonyStyle $io): void
    {
        $progressBar = $io->createProgressBar(count($clients));
        $progressBar->start();

        foreach ($clients as $client) {
            // Eliminar en orden inverso para respetar las foreign keys
            foreach ($client->getBillingRecords() as $billingRecord) {
                $this->entityManager->remove($billingRecord);
            }
            foreach ($client->getInvoices() as $invoice) {
                $this->entityManager->remove($invoice);
            }
            foreach ($client->getAccessLogs() as $accessLog) {
                $this->entityManager->remove($accessLog);
            }
            foreach ($client->getSubscriptions() as $subscription) {
                $this->entityManager->remove($subscription);
            }
            $this->entityManager->remove($client);

            $progressBar->advance();
        }

        $this->entityManager->flush();
        $progressBar->finish();
        $io->newLine();
    }

    private function escapeString(?string $string): string
    {
        if ($string === null) {
            return '';
        }
        return str_replace(['\\', "'", "\n", "\r"], ['\\\\', "\\'", "\\n", "\\r"], $string);
    }
}
