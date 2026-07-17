<?php

declare(strict_types=1);

namespace Libxa\Console\Commands\Db;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class SeedCommand extends Command
{
    protected static $defaultName = 'db:seed';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('db:seed')
             ->setDescription('Seed the database with records')
             ->addOption('class', null, InputOption::VALUE_OPTIONAL, 'The seeder class to run', 'DatabaseSeeder');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->app->boot();

        $seedersPath = $this->app->basePath('src/database/seeders');
        $class       = $input->getOption('class');
        $file        = $seedersPath . '/' . $class . '.php';

        if (! file_exists($file)) {
            $output->writeln("<error>Seeder [{$class}] not found at {$file}.</error>");
            $output->writeln("<comment>Tip:</comment> run <info>php Libxa make:seeder {$class}</info> to create it.");
            return Command::FAILURE;
        }

        require_once $file;

        if (! class_exists($class)) {
            $output->writeln("<error>Seeder class [{$class}] was not declared in {$file}.</error>");
            return Command::FAILURE;
        }

        $output->writeln("<comment>Seeding:</comment> {$class}");

        (new $class())->run();

        $output->writeln("<info>Database seeded successfully.</info>");

        return Command::SUCCESS;
    }
}
