<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class QueueRestartCommand extends Command
{
    protected static $defaultName = 'queue:restart';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('queue:restart')
             ->setDescription('Signal all running queue workers to restart after their current job');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $signalFile = QueueWorkCommand::restartSignalPath($this->app);

        $directory = dirname($signalFile);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($signalFile, (string) microtime(true));

        $output->writeln("<info>Broadcasting queue restart signal.</info>");

        return Command::SUCCESS;
    }
}
