<?php

declare(strict_types=1);

namespace Libxa\Console\Commands\Npm;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Libxa\Foundation\Application;

/**
 * Npm Add Command
 *
 * Wraps `npm install` for LibxaFrame.
 */
class AddCommand extends Command
{
    protected static $defaultName = 'npm:add';

    public function __construct(protected Application $Libxa)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('npm:add')
             ->setDescription('Add NPM packages to the project')
             ->addArgument('packages', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The NPM package names')
             ->addOption('dev', 'D', InputOption::VALUE_NONE, 'Install as a development dependency');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $packages = $input->getArgument('packages');
        $isDev   = $input->getOption('dev');

        $packageString = implode(' ', $packages);
        $io->title("NPM: Adding Packages [{$packageString}]");

        if (! file_exists($this->Libxa->basePath('package.json'))) {
            $io->warning("package.json not found. Initializing...");
            passthru("npm init -y", $initResult);
            if ($initResult !== 0) {
                $io->error("Failed to initialize package.json.");
                return Command::FAILURE;
            }
        }

        $flag = $isDev ? '--save-dev' : '';
        $command = "npm install {$packageString} {$flag}";

        $io->text("Executing: <info>{$command}</info>");
        $io->newLine();

        passthru($command, $exitCode);

        if ($exitCode === 0) {
            $io->success("Packages [{$packageString}] added successfully!");
            return Command::SUCCESS;
        }

        $io->error("Failed to add packages [{$packageString}].");
        return Command::FAILURE;
    }
}
