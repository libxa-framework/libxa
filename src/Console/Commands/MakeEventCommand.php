<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class MakeEventCommand extends Command
{
    protected static $defaultName = 'make:event';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('make:event')
             ->setDescription('Create a new event class')
             ->addArgument('name', InputArgument::REQUIRED, 'The name of the event');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $path = $this->app->appPath("Events/{$name}.php");

        if (file_exists($path)) {
            $output->writeln("<error>Event already exists!</error>");
            return Command::FAILURE;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $stub = <<<PHP
<?php

declare(strict_types=1);

namespace App\Events;

class {$name}
{
    public function __construct(
        // public readonly \$payload,
    ) {}
}
PHP;

        file_put_contents($path, $stub);
        $output->writeln("<info>Event created successfully:</info> {$path}");

        return Command::SUCCESS;
    }
}
