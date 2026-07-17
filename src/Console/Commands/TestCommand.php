<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class TestCommand extends Command
{
    protected static $defaultName = 'test';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('test')
             ->setDescription('Run the application test suite (via PHPUnit)')
             ->addOption('filter', null, InputOption::VALUE_OPTIONAL, 'Only run tests matching this filter')
             ->addOption('testsuite', null, InputOption::VALUE_OPTIONAL, 'Only run this testsuite');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $binary = $this->app->basePath('vendor/bin/phpunit');

        if (! is_file($binary)) {
            $output->writeln("<error>PHPUnit is not installed.</error> Run <info>composer require --dev phpunit/phpunit</info> first.");
            return Command::FAILURE;
        }

        $command = [PHP_BINARY, $binary];

        if ($filter = $input->getOption('filter')) {
            $command[] = '--filter';
            $command[] = $filter;
        }

        if ($suite = $input->getOption('testsuite')) {
            $command[] = '--testsuite';
            $command[] = $suite;
        }

        $descriptors = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];

        $process = @proc_open($command, $descriptors, $pipes, $this->app->basePath(), ['APP_ENV' => 'testing'] + $_ENV);

        if (! is_resource($process)) {
            $output->writeln("<error>Unable to start PHPUnit process.</error>");
            return Command::FAILURE;
        }

        return proc_close($process);
    }
}
