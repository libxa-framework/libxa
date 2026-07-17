<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class MakeMigrationCommand extends Command
{
    protected static $defaultName = 'make:migration';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('make:migration')
             ->setDescription('Create a new migration file')
             ->addArgument('name', InputArgument::REQUIRED, 'The name of the migration')
             ->addOption('create', null, InputOption::VALUE_OPTIONAL, 'The table to be created')
             ->addOption('table', null, InputOption::VALUE_OPTIONAL, 'The table to migrate');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name  = $input->getArgument('name');
        $slug  = $this->slugify($name);
        $class = $this->classify($slug);

        $create = $input->getOption('create');
        $table  = $create ?: $input->getOption('table');

        // Infer table/intent from conventional names like
        // create_posts_table / add_column_to_posts_table when no option given.
        if (! $table && preg_match('/^create_(.+)_table$/', $slug, $m)) {
            $create = $table = $m[1];
        } elseif (! $table && preg_match('/_to_(.+)_table$/', $slug, $m)) {
            $table = $m[1];
        }

        $directory = $this->app->basePath('src/database/migrations');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = date('Y_m_d_His') . '_' . $slug . '.php';
        $path     = $directory . '/' . $filename;

        if (file_exists($path)) {
            $output->writeln("<error>A migration with this name already exists!</error>");
            return Command::FAILURE;
        }

        $stub = $create
            ? $this->createStub($class, $table)
            : $this->tableStub($class, $table ?: 'table_name');

        file_put_contents($path, $stub);

        $output->writeln("<info>Migration created successfully:</info> {$path}");

        return Command::SUCCESS;
    }

    protected function createStub(string $class, string $table): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

use Libxa\Atlas\Migrations\Migration;
use Libxa\Atlas\Schema\Blueprint;
use Libxa\Atlas\Schema\Schema;

class {$class} extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
}
PHP;
    }

    protected function tableStub(string $class, string $table): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

use Libxa\Atlas\Migrations\Migration;
use Libxa\Atlas\Schema\Blueprint;
use Libxa\Atlas\Schema\Schema;

class {$class} extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('{$table}', function (Blueprint \$table) {
            //
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('{$table}', function (Blueprint \$table) {
            //
        });
    }
}
PHP;
    }

    protected function slugify(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9_]+/', '_', $name);
        return strtolower(trim($name, '_'));
    }

    protected function classify(string $slug): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $slug)));
    }
}
