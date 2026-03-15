<?php

namespace App\Command;

use App\Repository\SubscriptionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notify-expiring-subscriptions',
    description: 'Lista (y opcionalmente notifica) las suscripciones que vencen pronto',
)]
class NotifyExpiringSubscriptionsCommand extends Command
{
    public function __construct(private SubscriptionRepository $subscriptionRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'days',
            'd',
            InputOption::VALUE_OPTIONAL,
            'Días de anticipación para considerar "próximo a vencer"',
            7
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');

        $io->title(sprintf('Suscripciones que vencen en los próximos %d días', $days));

        $expiring = $this->subscriptionRepository->findExpiringSoon($days);

        if (empty($expiring)) {
            $io->success('No hay suscripciones próximas a vencer.');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($expiring as $sub) {
            $client  = $sub->getClient();
            $rows[] = [
                $client->getId(),
                $client->getName() . ' ' . $client->getSurname(),
                $client->getEmail() ?? '—',
                $client->getPhone() ?? '—',
                $sub->getSubscriptionType()->getName(),
                $sub->getEndDate()?->format('d/m/Y'),
            ];
        }

        $io->table(
            ['ID', 'Cliente', 'Email', 'Teléfono', 'Tipo', 'Vence el'],
            $rows
        );

        $io->warning(sprintf('%d suscripción(es) próximas a vencer.', count($expiring)));
        $io->note('Para enviar emails configura MAILER_DSN en .env y amplía este comando.');

        return Command::SUCCESS;
    }
}
