<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Libxa\Foundation\Application;
use Libxa\Atlas\Migrations\Migrator;

class MigrateStatusCommand extends Command
{
    protected static $defaultName = 'migrate:status';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('migrate:status')
             ->setDescription('Show the status of migrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $migrator = new Migrator();
        $migrator->addPath($this->app->basePath('src/database/migrations'));

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

        $ran = $migrator->getRanMigrations();
        
        $io->title('Migration Status');
        
        if (empty($ran)) {
            $io->warning('No migrations have been run yet.');
        } else {
            $io->text("Total migrations run: <info>" . count($ran) . "</info>");
            $io->listing($ran);
        }

        return Command::SUCCESS;
    }
}
