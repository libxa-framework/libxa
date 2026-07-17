<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class MakeMiddlewareCommand extends Command
{
    protected static $defaultName = 'make:middleware';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('make:middleware')
             ->setDescription('Create a new HTTP middleware class')
             ->addArgument('name', InputArgument::REQUIRED, 'The name of the middleware');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        if (! str_ends_with($name, 'Middleware')) {
            $name .= 'Middleware';
        }

        $path = $this->app->appPath("Http/Middleware/{$name}.php");

        if (file_exists($path)) {
            $output->writeln("<error>Middleware already exists!</error>");
            return Command::FAILURE;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $stub = <<<PHP
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Libxa\Http\Request;
use Libxa\Http\Response;

class {$name}
{
    public function handle(Request \$request, \Closure \$next): Response
    {
        return \$next(\$request);
    }
}
PHP;

        file_put_contents($path, $stub);
        $output->writeln("<info>Middleware created successfully:</info> {$path}");

        return Command::SUCCESS;
    }
}
