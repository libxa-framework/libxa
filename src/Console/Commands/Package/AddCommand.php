<?php

declare(strict_types=1);

namespace Libxa\Console\Commands\Package;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Libxa\Foundation\Application;

class AddCommand extends Command
{

    public function __construct(protected Application $Libxa)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('package:add')
            ->setDescription('Install a Libxa package via Composer and discover it')
            ->addArgument('package', InputArgument::REQUIRED, 'The package name (vendor/package)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $package = $input->getArgument('package');

        $io->title("Installing Libxa Package: {$package}");

        // 1. Run Composer Require
        $io->text("Running <info>composer require {$package}</info>...");
        
        $command = "composer require {$package}";
        passthru($command, $exitCode);

        if ($exitCode !== 0) {
            $io->error("Failed to install package {$package}.");
            return Command::FAILURE;
        }

        // 2. Run Discovery
        $io->text("Triggering auto-discovery...");
        
        $discoveryCommand = $this->getApplication()->find('package:discover');
        $exitCode = $discoveryCommand->run($input, $output);

        if ($exitCode === 0) {
            $io->success("Package {$package} installed and discovered successfully.");
        }

        return $exitCode;
    }
}
