<?php

declare(strict_types=1);

namespace Libxa\Console\Commands\Route;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class ClearCommand extends Command
{
    protected static $defaultName = 'route:clear';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('route:clear')
             ->setDescription('Remove the cached routes file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheFile = $this->app->basePath('src/bootstrap/cache/routes.php');

        if (is_file($cacheFile)) {
            @unlink($cacheFile);
            $output->writeln("<info>Route cache cleared:</info> {$cacheFile}");
        } else {
            $output->writeln("<comment>No route cache file found — nothing to clear.</comment>");
        }

        return Command::SUCCESS;
    }
}
