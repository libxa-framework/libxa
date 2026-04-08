<?php

declare(strict_types=1);

namespace Libxa\Console\Commands\Npm;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Libxa\Foundation\Application;

/**
 * Npm Remove Command
 *
 * Wraps `npm uninstall` for LibxaFrame.
 */
class RemoveCommand extends Command
{
    protected static $defaultName = 'npm:remove';

    public function __construct(protected Application $Libxa)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('npm:remove')
             ->setDescription('Remove NPM packages from the project')
             ->addArgument('packages', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The NPM package names');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $packages = $input->getArgument('packages');

        $packageString = implode(' ', $packages);
        $io->title("NPM: Removing Packages [{$packageString}]");

        $command = "npm uninstall {$packageString}";

        $io->text("Executing: <info>{$command}</info>");
        $io->newLine();

        passthru($command, $exitCode);

        if ($exitCode === 0) {
            $io->success("Packages [{$packageString}] removed successfully!");
            return Command::SUCCESS;
        }

        $io->error("Failed to remove packages [{$packageString}].");
        return Command::FAILURE;
    }
}
