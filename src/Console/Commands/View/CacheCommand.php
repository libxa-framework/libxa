<?php

declare(strict_types=1);

namespace Libxa\Console\Commands\View;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class CacheCommand extends Command
{
    protected static $defaultName = 'view:cache';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('view:cache')
             ->setDescription('Precompile all Blade views so the first request never pays a compile cost');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->app->boot();

        if (! $this->app->has('blade')) {
            $output->writeln('<error>The "blade" view engine is not bound in the container.</error>');
            return Command::FAILURE;
        }

        // Start from a clean slate so stale compiled files (renamed/deleted
        // source views) never linger next to the fresh set.
        $blade   = $this->app->make('blade');
        $cleared = $blade->clearCache();

        $start     = microtime(true);
        $compiled  = $blade->precompileAll();
        $elapsedMs = round((microtime(true) - $start) * 1000, 1);

        if ($cleared > 0) {
            $output->writeln("<comment>Cleared {$cleared} stale compiled view(s).</comment>");
        }

        $count = count($compiled);
        $output->writeln("<info>Blade views cached successfully:</info> {$count} file(s) compiled in {$elapsedMs}ms.");
        $output->writeln('Remember to run <comment>view:cache</comment> again after every deploy. In production the ');
        $output->writeln('engine trusts these compiled files without checking source timestamps.');

        return Command::SUCCESS;
    }
}
