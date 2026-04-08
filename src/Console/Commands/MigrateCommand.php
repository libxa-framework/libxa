<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;
use Libxa\Atlas\Migrations\Migrator;

class MigrateCommand extends Command
{
    protected static $defaultName = 'migrate';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('migrate')
             ->setDescription('Run database migrations')
             ->addOption('fresh', null, InputOption::VALUE_NONE, 'Drop all tables and re-run all migrations')
             ->addOption('rollback', null, InputOption::VALUE_NONE, 'Rollback the last batch of migrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $migrator = new Migrator();
        
        // Add default migration path
        $migrator->addPath($this->app->databasePath('migrations'));

        // Scan modules for migrations
        $modulesPath = $this->app->basePath('src/app/Modules');
        if (is_dir($modulesPath)) {
            $modules = array_diff(scandir($modulesPath), ['.', '..']);
            foreach ($modules as $module) {
                $path = $modulesPath . '/' . $module . '/Migrations';
                if (is_dir($path)) {
                    $migrator->addPath($path);
                }
            }
        }

        if ($input->getOption('fresh')) {
            $output->writeln("<comment>Dropping all tables...</comment>");
            $migrator->fresh();
            $output->writeln("<info>Database refreshed successfully.</info>");
            return Command::SUCCESS;
        }

        if ($input->getOption('rollback')) {
            $output->writeln("<comment>Rolling back migrations...</comment>");
            $migrator->rollback();
            $output->writeln("<info>Rollback complete.</info>");
            return Command::SUCCESS;
        }

        $output->writeln("<comment>Running migrations...</comment>");
        $migrator->run();
        $output->writeln("<info>Migrations complete.</info>");

        return Command::SUCCESS;
    }
}
