<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;
use Libxa\Atlas\Migrations\Migrator;

class MigrateRollbackCommand extends Command
{
    protected static $defaultName = 'migrate:rollback';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('migrate:rollback')
             ->setDescription('Rollback the last database migration batch')
             ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'The number of batches to rollback', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->app->boot();

        $migrator = $this->buildMigrator();
        $steps    = max(1, (int) $input->getOption('step'));

        $output->writeln("<comment>Rolling back migrations...</comment>");

        $totalRolledBack = 0;

        for ($i = 0; $i < $steps; $i++) {
            $results = $migrator->rollback();

            if (empty($results)) {
                break;
            }

            foreach ($results as $migration) {
                $output->writeln("  <info>Rolled back:</info> {$migration}");
            }

            $totalRolledBack += count($results);
        }

        if ($totalRolledBack === 0) {
            $output->writeln("<info>Nothing to rollback.</info>");
        } else {
            $output->writeln("<info>{$totalRolledBack} migration(s) rolled back successfully.</info>");
        }

        return Command::SUCCESS;
    }

    protected function buildMigrator(): Migrator
    {
        $migrator = new Migrator();
        $migrator->addPath($this->app->basePath('src/database/migrations'));

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

        return $migrator;
    }
}
