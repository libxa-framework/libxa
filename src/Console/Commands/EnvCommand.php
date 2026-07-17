<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class EnvCommand extends Command
{
    protected static $defaultName = 'env';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('env')
             ->setDescription('Display the current application environment');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $env = Application::env('APP_ENV', 'local');
        $output->writeln("The application environment is <info>{$env}</info>.");

        $envFile = $this->app->basePath('.env');
        $output->writeln(is_file($envFile)
            ? "<comment>.env file:</comment> {$envFile}"
            : "<comment>No .env file found</comment> — using defaults / environment variables.");

        return Command::SUCCESS;
    }
}
