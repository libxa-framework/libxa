<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class MakeCommandCommand extends Command
{
    protected static $defaultName = 'make:command';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('make:command')
             ->setDescription('Create a new console command')
             ->addArgument('name', InputArgument::REQUIRED, 'The name of the command class')
             ->addArgument('signature', InputArgument::OPTIONAL, 'The terminal signature (e.g. "report:send")', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        if (! str_ends_with($name, 'Command')) {
            $name .= 'Command';
        }

        $signature = $input->getArgument('signature') ?? $this->guessSignature($name);

        $path = $this->app->appPath("Console/Commands/{$name}.php");

        if (file_exists($path)) {
            $output->writeln("<error>Command already exists!</error>");
            return Command::FAILURE;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $stub = <<<PHP
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class {$name} extends Command
{
    public function __construct(protected Application \$app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        \$this->setName('{$signature}')
             ->setDescription('Describe what this command does.');
    }

    protected function execute(InputInterface \$input, OutputInterface \$output): int
    {
        \$output->writeln('<info>{$name} ran successfully.</info>');

        return Command::SUCCESS;
    }
}
PHP;

        file_put_contents($path, $stub);
        $output->writeln("<info>Command created successfully:</info> {$path}");
        $output->writeln("It will be auto-discovered from app/Console/Commands and available as <comment>php Libxa {$signature}</comment>.");

        return Command::SUCCESS;
    }

    protected function guessSignature(string $className): string
    {
        $base  = preg_replace('/Command$/', '', $className);
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $base));

        return $snake;
    }
}
