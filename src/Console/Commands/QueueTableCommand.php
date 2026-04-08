<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class QueueTableCommand extends Command
{
    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('queue:table')
            ->setDescription('Create a migration for the queue jobs database table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $migrationFile = $this->app->basePath('src/database/migrations/' . date('Y_m_d_His') . '_create_jobs_table.php');
        
        $content = <<<'PHP'
<?php

use Libxa\Atlas\Schema\Blueprint;
use Libxa\Atlas\DB;

class CreateJobsTable
{
    public function up(): void
    {
        DB::schema()->create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->text('payload');
            $table->integer('attempts')->default(0);
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at');
            $table->integer('created_at');
        });
    }

    public function down(): void
    {
        DB::schema()->dropIfExists('jobs');
    }
}
PHP;
        file_put_contents($migrationFile, $content);
        
        $output->writeln('<info>Migration for "jobs" table created successfully.</info>');
        $output->writeln('<comment>Run "php Libxa migrate" to apply it.</comment>');

        return Command::SUCCESS;
    }
}
