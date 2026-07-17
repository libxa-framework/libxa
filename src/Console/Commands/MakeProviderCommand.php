<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class MakeProviderCommand extends Command
{
    protected static $defaultName = 'make:provider';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('make:provider')
             ->setDescription('Create a new service provider class')
             ->addArgument('name', InputArgument::REQUIRED, 'The name of the service provider');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        if (! str_ends_with($name, 'ServiceProvider')) {
            $name .= 'ServiceProvider';
        }

        $path = $this->app->appPath("Providers/{$name}.php");

        if (file_exists($path)) {
            $output->writeln("<error>Provider already exists!</error>");
            return Command::FAILURE;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $stub = <<<PHP
<?php

declare(strict_types=1);

namespace App\Providers;

use Libxa\Container\ServiceProvider;

class {$name} extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
PHP;

        file_put_contents($path, $stub);
        $output->writeln("<info>Provider created successfully:</info> {$path}");
        $output->writeln("<comment>Remember to register it in your config/app.php (or bootstrap) providers list.</comment>");

        return Command::SUCCESS;
    }
}
