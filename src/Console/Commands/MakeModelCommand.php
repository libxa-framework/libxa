<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class MakeModelCommand extends Command
{
    protected static $defaultName = 'make:model';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('make:model')
             ->setDescription('Create a new Atlas model class')
             ->addArgument('name', InputArgument::REQUIRED, 'The name of the model')
             ->addOption('migration', 'm', InputOption::VALUE_NONE, 'Also create a migration for the model')
             ->addOption('table', 't', InputOption::VALUE_OPTIONAL, 'The table name (defaults to snake_case plural of the model name)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name  = trim($input->getArgument('name'));
        $table = $input->getOption('table') ?: $this->tableize($name);

        $path = $this->app->appPath("Models/{$name}.php");

        if (file_exists($path)) {
            $output->writeln("<error>Model already exists!</error>");
            return Command::FAILURE;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $stub = <<<PHP
<?php

declare(strict_types=1);

namespace App\Models;

use Libxa\Atlas\Model;

class {$name} extends Model
{
    protected string \$table = '{$table}';

    protected array \$fillable = [
        //
    ];

    protected array \$hidden = [
        //
    ];
}
PHP;

        file_put_contents($path, $stub);
        $output->writeln("<info>Model created successfully:</info> {$path}");

        if ($input->getOption('migration')) {
            $this->getApplication()
                ->find('make:migration')
                ->run(new \Symfony\Component\Console\Input\ArrayInput([
                    'name'    => "create_{$table}_table",
                    '--create' => $table,
                ]), $output);
        }

        return Command::SUCCESS;
    }

    /**
     * Convert a StudlyCase model name into a snake_case plural table name
     * (e.g. "BlogPost" -> "blog_posts").
     */
    protected function tableize(string $name): string
    {
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));

        return str_ends_with($snake, 'y')
            ? substr($snake, 0, -1) . 'ies'
            : $snake . 's';
    }
}
