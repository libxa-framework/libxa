<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class StorageLinkCommand extends Command
{
    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('storage:link')
            ->setDescription('Create a symbolic link from "src/storage/app/public" to "src/public/storage"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = $this->app->basePath('src/public/storage');
        $source = $this->app->basePath('src/storage/app/public');

        if (!is_dir($source)) {
            mkdir($source, 0755, true);
        }

        if (file_exists($target)) {
            $output->writeln('<error>The "src/public/storage" directory already exists.</error>');
            return Command::FAILURE;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            // Use mklink /D for Windows
            $command = sprintf('mklink /D "%s" "%s"', $target, $source);
            exec($command, $cliOutput, $returnVar);
            
            if ($returnVar !== 0) {
                // Try junction as fallback if symlink fails (requires admin usually)
                $command = sprintf('mklink /J "%s" "%s"', $target, $source);
                exec($command, $cliOutput, $returnVar);
            }
        } else {
            $returnVar = symlink($source, $target) ? 0 : 1;
        }

        if ($returnVar === 0) {
            $output->writeln('<info>The [src/public/storage] link has been connected to [src/storage/app/public].</info>');
            return Command::SUCCESS;
        }

        $output->writeln('<error>Failed to create the symbolic link.</error>');
        return Command::FAILURE;
    }
}
