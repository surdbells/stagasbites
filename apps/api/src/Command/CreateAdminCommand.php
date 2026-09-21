<?php

declare(strict_types=1);

namespace StagasBites\Command;

use StagasBites\Entity\User;
use StagasBites\Entity\UserRole;
use StagasBites\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:create-admin', description: 'Create an admin user, or promote an existing account')]
final class CreateAdminCommand extends Command
{
    public function __construct(private readonly UserRepository $users)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Admin email address');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $io->error('That is not a valid email address.');

            return Command::INVALID;
        }

        $user = $this->users->findByEmail($email);
        if ($user !== null) {
            $user->setRole(UserRole::ADMIN);
            $this->users->save($user);
            $io->success("Existing account {$email} promoted to admin.");

            return Command::SUCCESS;
        }

        // The password is read from a hidden prompt (or ADMIN_PASSWORD in CI) so it never lands in shell history.
        $password = $_SERVER['ADMIN_PASSWORD'] ?? $io->askQuestion((new Question('Password (min 10 chars)'))->setHidden(true));
        if (!is_string($password) || strlen($password) < 10) {
            $io->error('Password must be at least 10 characters.');

            return Command::INVALID;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Store');
        $user->setLastName('Admin');
        $user->setRole(UserRole::ADMIN);
        $user->setPassword($password);
        $this->users->save($user);

        $io->success("Admin {$email} created.");

        return Command::SUCCESS;
    }
}
