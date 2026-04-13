<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class MakeAdminWidgetCommand extends Command
{
    protected static $defaultName = 'make:admin-widget';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('make:admin-widget')
             ->setDescription('Create a new custom LibAdmin widget class')
             ->addArgument('name', InputArgument::REQUIRED, 'The name of the Widget');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        if (! str_ends_with($name, 'Widget')) {
            $name .= 'Widget';
        }

        // Derive a snake_case view name from widget name (e.g. RevenueWidget → revenue)
        $viewKey  = strtolower(preg_replace('/Widget$/', '', $name));
        $viewName = 'admin.widgets.' . $viewKey;

        $path = $this->app->appPath("Admin/Widgets/{$name}.php");

        if (file_exists($path)) {
            $output->writeln("<error>Admin Widget already exists!</error>");
            return Command::FAILURE;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $stub = <<<PHP
<?php

declare(strict_types=1);

namespace App\Admin\Widgets;

use Libxa\Admin\Widgets\Widget;

class {$name} extends Widget
{
    /**
     * The Blade view corresponding to this widget.
     * @var string
     */
    protected static string \$view = '{$viewName}';

    /**
     * Define the column span inside the grid (1 to 12).
     * @var int
     */
    protected int \$columnSpan = 12;

    /**
     * Data to pass to the view.
     */
    protected function getViewData(): array
    {
        return [
            'data' => 'Your dynamic data here',
        ];
    }
}
PHP;

        file_put_contents($path, $stub);

        // Auto-create the corresponding Blade view
        $viewRelative = str_replace('.', DIRECTORY_SEPARATOR, $viewName) . '.blade.php';
        $viewPath     = $this->app->resourcePath('views/' . $viewRelative);
        $viewDir      = dirname($viewPath);

        if (! is_dir($viewDir)) {
            mkdir($viewDir, 0755, true);
        }

        if (! file_exists($viewPath)) {
            $viewStub = <<<BLADE
<div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
    <div class="flex items-center gap-3 mb-4">
        <div class="p-2 bg-primary-container text-primary rounded-xl">
            <span class="material-symbols-outlined text-sm">widgets</span>
        </div>
        <h3 class="text-base font-bold font-headline text-on-surface">{$name}</h3>
    </div>
    <p class="text-on-surface-variant text-sm">{{ \$data }}</p>
</div>
BLADE;
            file_put_contents($viewPath, $viewStub);
            $output->writeln("<info>Widget view created:</info> {$viewPath}");
        }

        $output->writeln("<info>Admin Widget created successfully:</info> {$path}");
        $output->writeln("<comment>Don't forget to register it in your AdminPanelProvider!</comment>");

        return Command::SUCCESS;
    }
}
