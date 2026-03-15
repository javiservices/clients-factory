<?php

namespace App\Command;

use App\Repository\ClientRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notify-inactive-clients',
    description: 'Lista los clientes que no han accedido en N días (posibles bajas)',
)]
class NotifyInactiveClientsCommand extends Command
{
    public function __construct(private ClientRepository $clientRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'days',
            'd',
            InputOption::VALUE_OPTIONAL,
            'Número de días sin acceso para considerar un cliente inactivo',
            30
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');

        $io->title(sprintf('Clientes sin acceso en los últimos %d días', $days));

        $threshold = (new \DateTime())->modify('-' . $days . ' days');
        $allClients = $this->clientRepository->findAll();

        $inactive = [];
        foreach ($allClients as $client) {
            // Sólo clientes con suscripción activa y que llevan tiempo sin venir
            $lastSub = $client->getSubscriptions()->last() ?: null;
            if (!$lastSub || !$lastSub->isActive()) {
                continue;
            }

            $lastAccess = $client->getLastAccess();
            if ($lastAccess === null || $lastAccess < $threshold) {
                $inactive[] = [
                    $client->getId(),
                    $client->getName() . ' ' . $client->getSurname(),
                    $client->getEmail() ?? '—',
                    $client->getPhone() ?? '—',
                    $lastAccess ? $lastAccess->format('d/m/Y') : 'Nunca',
                    $lastSub->getSubscriptionType()->getName(),
                ];
            }
        }

        if (empty($inactive)) {
            $io->success(sprintf('No hay clientes inactivos con más de %d días sin acceso.', $days));
            return Command::SUCCESS;
        }

        $io->table(
            ['ID', 'Cliente', 'Email', 'Teléfono', 'Último acceso', 'Suscripción'],
            $inactive
        );

        $io->warning(sprintf('%d cliente(s) inactivo(s) detectado(s).', count($inactive)));
        $io->note('Para enviar emails configura MAILER_DSN en .env y amplía este comando.');

        return Command::SUCCESS;
    }
}
