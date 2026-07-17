<?php

declare(strict_types=1);

namespace Libxa\Console\Commands\Config;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class ClearCommand extends Command
{
    protected static $defaultName = 'config:clear';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('config:clear')
             ->setDescription('Remove the cached configuration file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheFile = $this->app->basePath('src/bootstrap/cache/config.php');

        if (is_file($cacheFile)) {
            @unlink($cacheFile);
            $output->writeln("<info>Configuration cache cleared:</info> {$cacheFile}");
        } else {
            $output->writeln("<comment>No configuration cache file found — nothing to clear.</comment>");
        }

        return Command::SUCCESS;
    }
}
