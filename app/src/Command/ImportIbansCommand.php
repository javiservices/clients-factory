<?php

namespace App\Command;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-ibans',
    description: 'Importa y asigna números de cuenta bancaria (IBAN) a los clientes desde un archivo JSON'
)]
class ImportIbansCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('json_file', InputArgument::OPTIONAL, 'Ruta al archivo JSON', 'ibans.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('json_file');

        if (!file_exists($file)) {
            $io->error("Archivo no encontrado: {$file}");
            return Command::FAILURE;
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (!is_array($data)) {
            $io->error("El contenido del archivo no es un JSON válido");
            return Command::FAILURE;
        }

        $repo = $this->em->getRepository(Client::class);
        $updated = 0;
        $notFound = 0;

        foreach ($data as $clientId => $iban) {
            $client = $repo->find((int) $clientId);
            if (!$client) {
                $notFound++;
                continue;
            }

            $client->setIban($iban);
            $updated++;

            if ($updated % 50 === 0) {
                $this->em->flush();
            }
        }

        $this->em->flush();

        $io->success("Se han actualizado {$updated} clientes con su número de cuenta IBAN ({$notFound} no encontrados).");

        return Command::SUCCESS;
    }
}
