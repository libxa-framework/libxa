<?php

declare(strict_types=1);

namespace Libxa\Console\Commands\View;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class ClearCommand extends Command
{
    protected static $defaultName = 'view:clear';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('view:clear')
             ->setDescription('Clear all compiled Blade view files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->app->boot();

        if ($this->app->has('blade')) {
            $count = $this->app->make('blade')->clearCache();
            $output->writeln("<info>Compiled views cleared:</info> {$count} file(s) removed.");
            return Command::SUCCESS;
        }

        // Fall back to wiping the default storage/framework/views directory
        // directly if the blade service isn't bound for some reason.
        $viewsCache = $this->app->storagePath('framework/views');
        $files      = is_dir($viewsCache) ? glob($viewsCache . '/*.php') : [];
        $count      = 0;

        foreach ($files ?: [] as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }

        $output->writeln("<info>Compiled views cleared:</info> {$count} file(s) removed.");

        return Command::SUCCESS;
    }
}
