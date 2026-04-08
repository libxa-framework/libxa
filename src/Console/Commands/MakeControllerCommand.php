<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class MakeControllerCommand extends Command
{
    protected static $defaultName = 'make:controller';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('make:controller')
             ->setDescription('Create a new controller class')
             ->addArgument('name', InputArgument::REQUIRED, 'The name of the controller');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        if (! str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }

        $path = $this->app->appPath("Http/Controllers/{$name}.php");

        if (file_exists($path)) {
            $output->writeln("<error>Controller already exists!</error>");
            return Command::FAILURE;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $stub = <<<PHP
<?php

namespace App\Http\Controllers;

use Libxa\Http\Request;
use Libxa\Http\Response;

class {$name}
{
    public function index(Request \$request): Response
    {
        return view('welcome');
    }
}
PHP;

        file_put_contents($path, $stub);

        $output->writeln("<info>Controller created successfully:</info> {$path}");

        return Command::SUCCESS;
    }
}
