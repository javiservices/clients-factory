<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\Subscription;
use App\Entity\SubscriptionType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-clients',
    description: 'Importa clientes y suscripciones desde un archivo JSON normalizado generado desde el Excel',
)]
class ImportClientsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'json_file',
            InputArgument::OPTIONAL,
            'Ruta al archivo JSON',
            'clients_import.json'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $jsonFile = $input->getArgument('json_file');

        if (!file_exists($jsonFile)) {
            $io->error(sprintf('No se encontró el archivo: %s', $jsonFile));
            return Command::FAILURE;
        }

        $io->title('Importación de clientes al sistema GymAdmin');
        $data = json_decode(file_get_contents($jsonFile), true);
        if (!$data || !is_array($data)) {
            $io->error('El archivo JSON es inválido o está vacío.');
            return Command::FAILURE;
        }

        $total = count($data);
        $io->info(sprintf('Registros a procesar: %d', $total));

        // 1. Obtener o crear el SubscriptionType SALA-FITNESS
        $subTypeRepo = $this->entityManager->getRepository(SubscriptionType::class);
        $subType = $subTypeRepo->findOneBy(['name' => 'SALA-FITNESS']);
        if (!$subType) {
            $subType = new SubscriptionType();
            $subType->setName('SALA-FITNESS');
            $subType->setPrice(50.0);
            $subType->setMonths(1);
            $subType->setIsDailyPass(false);
            $this->entityManager->persist($subType);
            $this->entityManager->flush();
            $io->success('Creado tipo de suscripción: SALA-FITNESS (50€ / mensual)');
        }

        $subTypeId = $subType->getId();

        // 2. Configurar generador de IDs asignados
        $this->configureAssignedGenerator();

        $progressBar = new ProgressBar($output, $total);
        $progressBar->start();

        $batchSize = 100;
        $activeCount = 0;
        $bajaCount = 0;
        $subCount = 0;

        foreach ($data as $i => $item) {
            $client = new Client();
            $client->setId((int) $item['id']);
            $client->setName($item['name'] ?: 'Cliente');
            $client->setSurname($item['surname'] ?: '');
            $client->setIdNumber($item['id_number']);
            $client->setAddress($item['address']);
            $client->setLocality($item['locality']);
            $client->setZipCode($item['zip_code']);
            $client->setPhone($item['phone']);
            $client->setBillable(true);

            if ($item['birth_date']) {
                $client->setBirthDate(new \DateTime($item['birth_date']));
            }
            if ($item['start_date']) {
                $client->setStartDate(new \DateTime($item['start_date']));
            } else {
                $client->setStartDate(new \DateTime('2000-01-01'));
            }

            $isBaja = !empty($item['baja_date']);
            if ($isBaja) {
                $bajaCount++;
            } else {
                $activeCount++;
            }

            // Suscripción
            if (!empty($item['cuota'])) {
                $subscription = new Subscription();
                $subscription->setClient($client);

                // Reasociar el subscriptionType
                $currentSubType = $this->entityManager->getReference(SubscriptionType::class, $subTypeId);
                $subscription->setSubscriptionType($currentSubType);

                if (!$isBaja) {
                    // Cliente activo con cuota vigente
                    $subscription->setIsActive(true);
                    $subStart = $item['cuota_inicio'] ? new \DateTime($item['cuota_inicio']) : ($item['start_date'] ? new \DateTime($item['start_date']) : new \DateTime('2026-11-01'));
                    $subscription->setStartDate($subStart);
                    // Vence el 4 del mes siguiente al mes actual (el 5 ya no puede entrar)
                    $subscription->setEndDate(new \DateTime('2026-12-04 23:59:59'));
                    $subscription->setLastPaymentDate(new \DateTime('2026-11-01 00:00:00'));
                } else {
                    // Cliente de baja con registro histórico
                    $subscription->setIsActive(false);
                    $subStart = $item['start_date'] ? new \DateTime($item['start_date']) : new \DateTime('2000-01-01');
                    $subEnd   = new \DateTime($item['baja_date']);
                    $subscription->setStartDate($subStart);
                    $subscription->setEndDate($subEnd);
                    $subscription->setLastPaymentDate($subEnd);
                }

                $this->entityManager->persist($subscription);
                $subCount++;
            }

            $this->entityManager->persist($client);

            if (($i + 1) % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                // Reconfigurar generador tras clear()
                $this->configureAssignedGenerator();
            }

            $progressBar->advance();
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
        $progressBar->finish();
        $io->newLine(2);

        // 3. Actualizar auto_increment al máximo ID + 1
        $conn = $this->entityManager->getConnection();
        $maxId = (int) $conn->fetchOne('SELECT MAX(id) FROM client');
        $nextId = $maxId + 1;
        $conn->executeStatement("ALTER TABLE client AUTO_INCREMENT = {$nextId}");

        $io->success(sprintf(
            'Importación finalizada con éxito: %d clientes importados (%d activos, %d de baja), %d suscripciones creadas. Próximo ID asignado: #%d',
            $total,
            $activeCount,
            $bajaCount,
            $subCount,
            $nextId
        ));

        return Command::SUCCESS;
    }

    private function configureAssignedGenerator(): void
    {
        $metadata = $this->entityManager->getClassMetadata(Client::class);
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $metadata->setIdGenerator(new AssignedGenerator());
    }
}

