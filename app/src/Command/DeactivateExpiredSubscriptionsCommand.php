<?php

namespace App\Command;

use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:deactivate-expired-subscriptions',
    description: 'Deactivates subscriptions that have expired',
)]
class DeactivateExpiredSubscriptionsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SubscriptionRepository $subscriptionRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $now = new \DateTime();
        
        // Obtener todas las suscripciones activas que han expirado
        $expiredSubscriptions = $this->subscriptionRepository->createQueryBuilder('s')
            ->where('s.isActive = TRUE')
            ->andWhere('s.endDate < CURRENT_TIMESTAMP()')
            ->getQuery()
            ->getResult();

        if (empty($expiredSubscriptions)) {
            $io->success('No hay suscripciones expiradas para desactivar.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($expiredSubscriptions as $subscription) {
            $subscription->setIsActive(false);
            $count++;
        }

        try {
            $this->entityManager->flush();
            $io->success(sprintf('Se han desactivado %d suscripciones expiradas.', $count));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error al actualizar las suscripciones: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}