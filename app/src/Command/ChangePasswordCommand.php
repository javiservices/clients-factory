<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:change-password',
    description: 'Cambia la contraseña de un usuario del sistema (ej. admin)',
)]
class ChangePasswordCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Email del usuario (por defecto toma el primer usuario/admin)')
            ->addArgument('password', InputArgument::OPTIONAL, 'Nueva contraseña');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        if (!$email) {
            $user = $this->userRepository->findOneBy([]);
            if (!$user) {
                $io->error('No se encontró ningún usuario en el sistema.');
                return Command::FAILURE;
            }
            $email = $user->getEmail();
        } else {
            $user = $this->userRepository->findOneBy(['email' => $email]);
            if (!$user) {
                $io->error(sprintf('No se encontró el usuario con email: %s', $email));
                return Command::FAILURE;
            }
        }

        if (!$password) {
            $helper = $this->getHelper('question');
            $question = new Question(sprintf('Introduce la nueva contraseña para %s: ', $email));
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $question);
        }

        if (empty($password)) {
            $io->error('La contraseña no puede estar vacía.');
            return Command::FAILURE;
        }

        if (strlen($password) < 6) {
            $io->error('La contraseña debe tener al menos 6 caracteres.');
            return Command::FAILURE;
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        $this->entityManager->flush();

        $io->success(sprintf('Contraseña actualizada correctamente para el usuario %s.', $email));

        return Command::SUCCESS;
    }
}
