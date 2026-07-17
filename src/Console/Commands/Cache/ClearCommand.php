<?php

declare(strict_types=1);

namespace Libxa\Console\Commands\Cache;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class ClearCommand extends Command
{
    protected static $defaultName = 'cache:clear';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('cache:clear')
             ->setDescription('Flush the application cache');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->app->boot();

        if (! $this->app->has('cache')) {
            $output->writeln("<comment>No cache service is registered — nothing to clear.</comment>");
            return Command::SUCCESS;
        }

        $this->app->make('cache')->flush();

        $output->writeln("<info>Application cache cleared successfully.</info>");

        return Command::SUCCESS;
    }
}
