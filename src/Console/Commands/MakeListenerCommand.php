<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class MakeListenerCommand extends Command
{
    protected static $defaultName = 'make:listener';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('make:listener')
             ->setDescription('Create a new event listener class')
             ->addArgument('name', InputArgument::REQUIRED, 'The name of the listener')
             ->addOption('event', 'e', InputOption::VALUE_OPTIONAL, 'The event class this listener handles');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name  = $input->getArgument('name');
        $event = $input->getOption('event') ?: 'Event';

        $path = $this->app->appPath("Listeners/{$name}.php");

        if (file_exists($path)) {
            $output->writeln("<error>Listener already exists!</error>");
            return Command::FAILURE;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $eventImport = str_contains($event, '\\') ? $event : "App\\Events\\{$event}";
        $eventShort  = substr($eventImport, strrpos($eventImport, '\\') + 1);

        $stub = <<<PHP
<?php

declare(strict_types=1);

namespace App\Listeners;

use {$eventImport};

class {$name}
{
    /**
     * Handle the event.
     */
    public function handle({$eventShort} \$event): void
    {
        //
    }
}
PHP;

        file_put_contents($path, $stub);
        $output->writeln("<info>Listener created successfully:</info> {$path}");
        $output->writeln("<comment>Register it with:</comment> EventBus::listen({$eventShort}::class, {$name}::class);");

        return Command::SUCCESS;
    }
}
