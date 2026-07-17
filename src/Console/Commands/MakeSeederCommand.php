<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class MakeSeederCommand extends Command
{
    protected static $defaultName = 'make:seeder';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('make:seeder')
             ->setDescription('Create a new database seeder')
             ->addArgument('name', InputArgument::REQUIRED, 'The name of the seeder');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        if (! str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        $path = $this->app->basePath("src/database/seeders/{$name}.php");

        if (file_exists($path)) {
            $output->writeln("<error>Seeder already exists!</error>");
            return Command::FAILURE;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $stub = <<<PHP
<?php

declare(strict_types=1);

use Libxa\Atlas\Seeder;

class {$name} extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        //
    }
}
PHP;

        file_put_contents($path, $stub);
        $output->writeln("<info>Seeder created successfully:</info> {$path}");

        return Command::SUCCESS;
    }
}
