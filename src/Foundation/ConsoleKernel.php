<?php

declare(strict_types=1);

namespace Libxa\Foundation;

use Libxa\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console Kernel
 *
 * Manages the lifecycle of a CLI command execution.
 */
class ConsoleKernel
{
    public function __construct(protected Application $app) {}

    /**
     * Handle an incoming console command.
     */
    public function handle(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->app->boot();

            $console = $this->app->make(ConsoleApplication::class);
            return $console->run($input, $output);
        } catch (\Throwable $e) {
            $output->writeln("<error> [Libxa Error] {$e->getMessage()} </error>");
            $output->writeln($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Perform any termination logic.
     */
    public function terminate(int $status): void
    {
        // Close DB connections, log metrics, etc.
    }
}
