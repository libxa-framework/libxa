<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class MakeAdminPageCommand extends Command
{
    protected static $defaultName = 'make:admin-page';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('make:admin-page')
             ->setDescription('Create a new custom LibAdmin page class')
             ->addArgument('name', InputArgument::REQUIRED, 'The name of the Page');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        if (! str_ends_with($name, 'Page')) {
            $name .= 'Page';
        }

        $path = $this->app->appPath("Admin/Pages/{$name}.php");

        if (file_exists($path)) {
            $output->writeln("<error>Admin Page already exists!</error>");
            return Command::FAILURE;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $stub = <<<PHP
<?php

declare(strict_types=1);

namespace App\Admin\Pages;

use Libxa\Admin\Pages\Page;

class {$name} extends Page
{
    /**
     * The Blade view corresponding to this page.
     * @var string
     */
    protected static string \$view = 'admin.pages.custom_page';
    
    /**
     * Label in the navigation menu.
     * @var string
     */
    protected static string \$navigationLabel = 'Custom Page';
    
    /**
     * Icon for the navigation menu (Material Symbols).
     * @var string
     */
    protected static string \$icon = 'description';
    
    /**
     * Group in the navigation menu.
     * @var string
     */
    protected static string \$navigationGroup = 'App';
}
PHP;

        file_put_contents($path, $stub);

        $output->writeln("<info>Admin Page created successfully:</info> {$path}");
        $output->writeln("<comment>Don't forget to register it in your AdminPanelProvider!</comment>");

        return Command::SUCCESS;
    }
}
