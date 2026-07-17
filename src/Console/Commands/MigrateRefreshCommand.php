<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;
use Libxa\Atlas\Migrations\Migrator;

class MigrateRefreshCommand extends Command
{
    protected static $defaultName = 'migrate:refresh';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('migrate:refresh')
             ->setDescription('Rollback all migrations and re-run them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->app->boot();

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

        $output->writeln("<comment>Rolling back all migrations...</comment>");

        $rolledBack = 0;
        while (true) {
            $results = $migrator->rollback();
            if (empty($results)) {
                break;
            }
            foreach ($results as $migration) {
                $output->writeln("  <info>Rolled back:</info> {$migration}");
            }
            $rolledBack += count($results);
        }

        $output->writeln($rolledBack > 0
            ? "<info>{$rolledBack} migration(s) rolled back.</info>"
            : "<info>Nothing to rollback.</info>");

        $output->writeln("<comment>Re-running migrations...</comment>");

        $results = $migrator->run();
        foreach ($results as $migration) {
            $output->writeln("  <info>Migrated:</info> {$migration}");
        }

        $output->writeln("<info>Database refreshed successfully (" . count($results) . " migration(s) re-run).</info>");

        return Command::SUCCESS;
    }
}
