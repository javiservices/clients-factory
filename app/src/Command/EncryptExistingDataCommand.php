<?php

namespace App\Command;

use App\Service\EncryptionService;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cifra los datos personales existentes en la tabla client.
 *
 * Debe ejecutarse UNA VEZ tras desplegar la migración Version20260317000001.
 * Los campos ya cifrados se omiten automáticamente (idempotente).
 *
 * Cumplimiento LOPD-GDD (LO 3/2018) / RGPD (UE 2016/679)
 *
 * Uso:
 *   php bin/console app:encrypt-existing-data
 *   php bin/console app:encrypt-existing-data --dry-run   # simula sin escribir
 */
#[AsCommand(
    name: 'app:encrypt-existing-data',
    description: 'Cifra los datos personales de clientes existentes en BD (LOPD/RGPD)',
)]
class EncryptExistingDataCommand extends Command
{
    /** Campos de texto a cifrar */
    private const STRING_FIELDS = [
        'name',
        'surname',
        'address',
        'locality',
        'zip_code',
        'phone',
        'email',
        'id_number',
        'card_code',
    ];

    /** Campos de fecha a cifrar (almacenados como TEXT tras la migración) */
    private const DATE_FIELDS = [
        'birth_date',
    ];

    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Simula el proceso sin modificar la base de datos'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Cifrado de datos personales – LOPD/RGPD');

        if ($dryRun) {
            $io->warning('Modo simulación (--dry-run): no se escribirá nada en la BD.');
        }

        // Obtener todos los clientes directamente por DBAL (sin pasar por ORM/tipos)
        $clients = $this->connection->fetchAllAssociative('SELECT * FROM client');
        $total   = count($clients);

        if ($total === 0) {
            $io->success('No hay clientes en la base de datos.');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Procesando %d clientes...', $total));

        $progressBar = new ProgressBar($output, $total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% – %message%');
        $progressBar->setMessage('Iniciando...');
        $progressBar->start();

        $encrypted  = 0;
        $skipped    = 0;
        $errors     = 0;

        foreach ($clients as $row) {
            $id      = $row['id'];
            $updates = [];
            $params  = ['id' => $id];

            $progressBar->setMessage(sprintf('ID #%d', $id));

            // ── Campos de texto ─────────────────────────────────────────
            foreach (self::STRING_FIELDS as $field) {
                $value = $row[$field];
                if ($value === null || $value === '') {
                    continue;
                }
                if (EncryptionService::isEncrypted($value)) {
                    // Ya cifrado en una ejecución anterior
                    continue;
                }
                $updates[$field] = EncryptionService::encryptValue($value);
                $params[$field]  = $updates[$field];
            }

            // ── Campos de fecha ──────────────────────────────────────────
            foreach (self::DATE_FIELDS as $field) {
                $value = $row[$field];
                if ($value === null || $value === '') {
                    continue;
                }
                if (EncryptionService::isEncrypted($value)) {
                    continue;
                }
                // MySQL devuelve DATE como 'YYYY-MM-DD' – ciframos ese string
                $updates[$field] = EncryptionService::encryptValue($value);
                $params[$field]  = $updates[$field];
            }

            if (empty($updates)) {
                ++$skipped;
            } else {
                if (!$dryRun) {
                    try {
                        $setClauses = implode(', ', array_map(
                            static fn (string $col) => "$col = :$col",
                            array_keys($updates)
                        ));

                        $this->connection->executeStatement(
                            "UPDATE client SET $setClauses WHERE id = :id",
                            $params
                        );
                        ++$encrypted;
                    } catch (\Throwable $e) {
                        ++$errors;
                        $io->newLine();
                        $io->error(sprintf('Error en cliente #%d: %s', $id, $e->getMessage()));
                    }
                } else {
                    ++$encrypted; // Contamos como "habría cifrado"
                }
            }

            $progressBar->advance();
        }

        $progressBar->setMessage('¡Completado!');
        $progressBar->finish();
        $io->newLine(2);

        $io->definitionList(
            ['Total clientes'     => $total],
            ['Cifrados'           => $encrypted . ($dryRun ? ' (simulado)' : '')],
            ['Ya estaban cifrados'=> $skipped],
            ['Errores'            => $errors],
        );

        if ($errors > 0) {
            $io->error(sprintf('%d clientes tuvieron errores durante el cifrado.', $errors));
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->note('Simulación completada. Ejecuta sin --dry-run para cifrar los datos.');
        } else {
            $io->success('Datos personales cifrados correctamente. Base de datos conforme con LOPD/RGPD.');
        }

        return Command::SUCCESS;
    }
}
