<?php

namespace App\Command;

use App\Entity\Client;
use App\Service\ImageOptimizerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:optimize-photos',
    description: 'Comprime y optimiza todas las fotos de perfil existentes reduciendo su peso a pocos KB'
)]
class OptimizePhotosCommand extends Command
{
    public function __construct(
        private readonly ImageOptimizerService $imageOptimizer,
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $parameterBag
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $uploadsDir = $this->parameterBag->get('uploads_directory');

        if (!is_dir($uploadsDir)) {
            $io->warning("El directorio de uploads no existe: $uploadsDir");
            return Command::SUCCESS;
        }

        $clients = $this->entityManager->getRepository(Client::class)->findAll();
        $totalProcessed = 0;
        $totalBytesSaved = 0;

        $io->title("Iniciando optimización de fotos de clientes en: $uploadsDir");

        foreach ($clients as $client) {
            $photo = $client->getPhoto();
            if (!$photo) {
                continue;
            }

            $filePath = $uploadsDir . '/' . $photo;
            if (!file_exists($filePath)) {
                continue;
            }

            $originalSize = filesize($filePath);

            // Si ya es un archivo WebP menor de 80KB, lo saltamos
            if (str_ends_with(strtolower($photo), '.webp') && $originalSize < 80 * 1024) {
                continue;
            }

            $io->text(sprintf(
                "Optimizando foto de cliente #%d (%s): %s (%.2f MB)...",
                $client->getId(),
                $client->getName(),
                $photo,
                $originalSize / 1024 / 1024
            ));

            $newFilename = $this->imageOptimizer->optimizeExistingFile($filePath, 600, 82);

            if ($newFilename) {
                $newPath = $uploadsDir . '/' . $newFilename;
                $newSize = filesize($newPath);
                $saved = $originalSize - $newSize;
                $totalBytesSaved += $saved;
                $totalProcessed++;

                $client->setPhoto($newFilename);

                $io->success(sprintf(
                    " -> Comprimido con éxito: de %.2f MB a %.2f KB (Ahorro del %.1f%%)",
                    $originalSize / 1024 / 1024,
                    $newSize / 1024,
                    ($saved / $originalSize) * 100
                ));
            } else {
                $io->error("No se pudo optimizar la imagen: $filePath");
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            "Optimización finalizada. %d fotos procesadas. Espacio total liberado: %.2f MB.",
            $totalProcessed,
            $totalBytesSaved / 1024 / 1024
        ));

        return Command::SUCCESS;
    }
}
