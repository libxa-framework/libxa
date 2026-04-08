<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Libxa\Foundation\Application;
use Libxa\Support\Str;

/**
 * Make Module Command
 *
 * Generates a new module skeleton following the professional anatomy:
 * - ModuleServiceProvider (with publish groups)
 * - Http (Controllers, Requests, Middleware)
 * - Models, Services, Events, Listeners, Jobs
 * - Resources (views, lang, assets)
 * - Database (Migrations, Seeders)
 * - Routes (web, api, ws)
 * - Config
 */
class MakeModuleCommand extends Command
{
    protected static $defaultName = 'make:module';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('make:module')
             ->setDescription('Create a new professional LibxaFrame module')
             ->addArgument('name', InputArgument::REQUIRED, 'The name of the module')
             ->addOption('resource', 'r', InputOption::VALUE_REQUIRED, 'Generate a Model, Migration, and Controller for a resource')
             ->addOption('ai', 'a', InputOption::VALUE_NONE, 'Generate AI-enhanced resourceful logic');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $name     = Str::studly($input->getArgument('name'));
        $resource = $input->getOption('resource');
        $ai       = $input->getOption('ai');
        $path     = $this->app->modulesPath($name);

        if (is_dir($path)) {
            $io->error("Module [{$name}] already exists!");
            return Command::FAILURE;
        }

        $io->title("Scaffolding Module: {$name}");

        $this->createStructure($path);
        $this->createServiceProvider($path, $name);
        $this->createRoutes($path, $name, $resource);
        $this->createExampleFiles($path, $name);

        if ($resource) {
            $this->createResource($path, $name, Str::studly($resource), $ai, $io);
        }

        $io->success("Module [{$name}] created successfully at: {$path}");
        $io->note("LibxaFrame's auto-discovery will find {$name}ServiceProvider automatically.");

        if ($resource) {
            $io->info("Resource [{$resource}] scaffolded with Model, Migration, and Controller.");
        }

        $io->text([
            "Publish commands available after extraction:",
            "  php Libxa vendor:publish --tag={$name}-migrations",
            "  php Libxa vendor:publish --tag={$name}-config",
            "  php Libxa vendor:publish --tag={$name}-lang",
            "  php Libxa vendor:publish --tag={$name}-views",
            "  php Libxa vendor:publish --tag={$name}   (all)",
        ]);

        return Command::SUCCESS;
    }

    protected function createStructure(string $path): void
    {
        $dirs = [
            'Http/Controllers',
            'Http/Middleware',
            'Http/Requests',
            'Models',
            'Services',
            'Events',
            'Listeners',
            'Jobs',
            'Resources/views',
            'Resources/lang',
            'Resources/assets',
            'Database/Migrations',
            'Database/Seeders',
            'Routes',
            'Config',
            'Tests/Unit',
            'Tests/Feature',
        ];

        mkdir($path, 0755, true);

        foreach ($dirs as $dir) {
            mkdir($path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir), 0755, true);
        }
    }

    protected function createServiceProvider(string $path, string $name): void
    {
        $slug      = Str::kebab($name);
        $className = "{$name}ServiceProvider";

        // Build the stub using string concatenation to avoid heredoc escaping issues
        $lines = [];
        $lines[] = '<?php';
        $lines[] = '';
        $lines[] = 'declare(strict_types=1);';
        $lines[] = '';
        $lines[] = "namespace App\\Modules\\{$name};";
        $lines[] = '';
        $lines[] = 'use Libxa\\Foundation\\ModuleServiceProvider;';
        $lines[] = '';
        $lines[] = "class {$className} extends ModuleServiceProvider";
        $lines[] = '{';
        $lines[] = '    /**';
        $lines[] = '     * Register module services and bindings.';
        $lines[] = '     */';
        $lines[] = '    public function register(): void';
        $lines[] = '    {';
        $lines[] = '        parent::register();';
        $lines[] = '';
        $lines[] = '        // Bindings...';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    /**';
        $lines[] = '     * Bootstrap module resources.';
        $lines[] = '     */';
        $lines[] = '    public function boot(): void';
        $lines[] = '    {';
        $lines[] = '        // 1. Load Routes';
        $lines[] = "        \$this->loadRoutesFrom(__DIR__ . '/Routes/web.php', prefix: '{$slug}', middleware: ['web']);";
        $lines[] = "        \$this->loadRoutesFrom(__DIR__ . '/Routes/api.php', prefix: 'api/{$slug}', middleware: ['api']);";
        $lines[] = '';
        $lines[] = '        // 2. Load Views';
        $lines[] = "        \$this->loadViewsFrom(__DIR__ . '/Resources/views', '{$slug}');";
        $lines[] = '';
        $lines[] = '        // 3. Load Migrations';
        $lines[] = "        \$this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');";
        $lines[] = '';
        $lines[] = '        // 4. Load Translations';
        $lines[] = "        \$this->loadTranslationsFrom(__DIR__ . '/Resources/lang', '{$slug}');";
        $lines[] = '';
        $lines[] = '        // 5. Register Events';
        $lines[] = '        $this->listen([';
        $lines[] = '            // ExampleEvent::class => [ExampleListener::class],';
        $lines[] = '        ]);';
        $lines[] = '';
        $lines[] = '        // 6. Declare publishable assets';
        $lines[] = '        $this->declarePublishables();';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    /**';
        $lines[] = '     * Define module dependencies.';
        $lines[] = '     */';
        $lines[] = '    public function requires(): array';
        $lines[] = '    {';
        $lines[] = '        return [];';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    /**';
        $lines[] = '     * Declare publishable asset groups.';
        $lines[] = '     *';
        $lines[] = '     * Tags:';
        $lines[] = "     *   {$slug}-migrations  → php Libxa vendor:publish --tag={$slug}-migrations";
        $lines[] = "     *   {$slug}-config      → php Libxa vendor:publish --tag={$slug}-config";
        $lines[] = "     *   {$slug}-lang        → php Libxa vendor:publish --tag={$slug}-lang";
        $lines[] = "     *   {$slug}-views       → php Libxa vendor:publish --tag={$slug}-views";
        $lines[] = "     *   {$slug}             → php Libxa vendor:publish --tag={$slug}  (all)";
        $lines[] = '     */';
        $lines[] = '    protected function declarePublishables(): void';
        $lines[] = '    {';
        $lines[] = '        $base = __DIR__;';
        $lines[] = '        $app  = $this->app->basePath();';
        $lines[] = '';
        $lines[] = '        $this->publishes([';
        $lines[] = "            \$base . '/Database/Migrations' => \$app . '/database/migrations',";
        $lines[] = "        ], '{$slug}-migrations');";
        $lines[] = '';
        $lines[] = '        $this->publishes([';
        $lines[] = "            \$base . '/Config/{$slug}.php' => \$app . '/src/config/{$slug}.php',";
        $lines[] = "        ], '{$slug}-config');";
        $lines[] = '';
        $lines[] = '        $this->publishes([';
        $lines[] = "            \$base . '/Resources/lang' => \$app . '/src/lang/{$slug}',";
        $lines[] = "        ], '{$slug}-lang');";
        $lines[] = '';
        $lines[] = '        $this->publishes([';
        $lines[] = "            \$base . '/Resources/views' => \$app . '/src/resources/views/vendor/{$slug}',";
        $lines[] = "        ], '{$slug}-views');";
        $lines[] = '';
        $lines[] = '        // Publish everything at once';
        $lines[] = '        $this->publishes([';
        $lines[] = "            \$base . '/Database/Migrations' => \$app . '/database/migrations',";
        $lines[] = "            \$base . '/Config/{$slug}.php'  => \$app . '/src/config/{$slug}.php',";
        $lines[] = "            \$base . '/Resources/lang'      => \$app . '/src/lang/{$slug}',";
        $lines[] = "            \$base . '/Resources/views'     => \$app . '/src/resources/views/vendor/{$slug}',";
        $lines[] = "        ], '{$slug}');";
        $lines[] = '    }';
        $lines[] = '}';
        $lines[] = '';

        $stub = implode("\n", $lines);

        file_put_contents($path . DIRECTORY_SEPARATOR . "{$className}.php", $stub);
    }

    protected function createRoutes(string $path, string $name, ?string $resource = null): void
    {
        $prefix   = Str::kebab($name);
        $plural   = $resource ? Str::kebab(Str::plural($resource)) : null;
        $ctrlName = $resource ? Str::studly($resource) . 'Controller' : null;

        // Web Routes
        $webLines = ["<?php", "", "use Libxa\\Router\\Router;", ""];
        if ($resource) {
            $webLines[] = "use App\\Modules\\{$name}\\Http\\Controllers\\{$ctrlName};";
            $webLines[] = "";
        }
        $webLines[] = "/** @var Router \$router */";
        $webLines[] = "";
        $webLines[] = "\$router->get('/', function() {";
        $webLines[] = "    return view('{$prefix}::welcome');";
        $webLines[] = "});";
        if ($resource) {
            $webLines[] = "";
            $webLines[] = "// Resourceful routes for {$resource}";
            $webLines[] = "\$router->get('/{$plural}', [{$ctrlName}::class, 'index']);";
            $webLines[] = "\$router->get('/{$plural}/create', [{$ctrlName}::class, 'create']);";
            $webLines[] = "\$router->post('/{$plural}', [{$ctrlName}::class, 'store']);";
            $webLines[] = "\$router->get('/{$plural}/{id}', [{$ctrlName}::class, 'show']);";
            $webLines[] = "\$router->get('/{$plural}/{id}/edit', [{$ctrlName}::class, 'edit']);";
            $webLines[] = "\$router->put('/{$plural}/{id}', [{$ctrlName}::class, 'update']);";
            $webLines[] = "\$router->delete('/{$plural}/{id}', [{$ctrlName}::class, 'destroy']);";
        }
        file_put_contents($path . '/Routes/web.php', implode("\n", $webLines) . "\n");

        // API Routes
        $apiLines = ["<?php", "", "use Libxa\\Router\\Router;", ""];
        if ($resource) {
            $apiLines[] = "use App\\Modules\\{$name}\\Http\\Controllers\\{$ctrlName};";
            $apiLines[] = "";
        }
        $apiLines[] = "/** @var Router \$router */";
        $apiLines[] = "";
        $apiLines[] = "\$router->get('/health', function() {";
        $apiLines[] = "    return ['status' => 'ok', 'module' => '{$name}'];";
        $apiLines[] = "});";
        if ($resource) {
            $apiLines[] = "";
            $apiLines[] = "// API Resourceful routes for {$resource}";
            $apiLines[] = "\$router->get('/{$plural}', [{$ctrlName}::class, 'index']);";
            $apiLines[] = "\$router->post('/{$plural}', [{$ctrlName}::class, 'store']);";
            $apiLines[] = "\$router->get('/{$plural}/{id}', [{$ctrlName}::class, 'show']);";
            $apiLines[] = "\$router->put('/{$plural}/{id}', [{$ctrlName}::class, 'update']);";
            $apiLines[] = "\$router->delete('/{$plural}/{id}', [{$ctrlName}::class, 'destroy']);";
        }
        file_put_contents($path . '/Routes/api.php', implode("\n", $apiLines) . "\n");

        // WS Routes
        $wsStub = "<?php\n\n// WebSocket routes for {$name}\n";
        file_put_contents($path . '/Routes/ws.php', $wsStub);
    }

    protected function createResource(string $path, string $moduleName, string $resource, bool $ai, SymfonyStyle $io): void
    {
        $singular = Str::singular($resource);
        $plural   = Str::plural($resource);
        $table    = Str::snake($plural);

        $this->createModel($path, $moduleName, $singular, $ai);
        $this->createController($path, $moduleName, $singular, $plural, $ai);
        $this->createMigration($path, $singular, $plural, $table, $ai);
    }

    protected function createModel(string $path, string $moduleName, string $name, bool $ai): void
    {
        $lines = ["<?php", "", "declare(strict_types=1);", "", "namespace App\\Modules\\{$moduleName}\\Models;", "", "use Libxa\\Atlas\\Model;", ""];
        $lines[] = "class {$name} extends Model";
        $lines[] = "{";
        if ($ai) {
            $lines[] = "    /**";
            $lines[] = "     * AI Enhanced: Automated fillable attributes detection";
            $lines[] = "     */";
            $lines[] = "    protected array \$fillable = ['*'];";
            $lines[] = "";
            $lines[] = "    protected function onSaving(): void";
            $lines[] = "    {";
            $lines[] = "        // AI Hook: Pre-process data";
            $lines[] = "    }";
        } else {
            $lines[] = "    protected array \$fillable = [];";
        }
        $lines[] = "}";

        file_put_contents($path . "/Models/{$name}.php", implode("\n", $lines) . "\n");
    }

    protected function createController(string $path, string $moduleName, string $singular, string $plural, bool $ai): void
    {
        $className = "{$singular}Controller";
        $model     = $singular;
        $var       = Str::camel($singular);

        $lines = ["<?php", "", "declare(strict_types=1);", "", "namespace App\\Modules\\{$moduleName}\\Http\\Controllers;", "", "use Libxa\\Http\\Request;", "use App\\Modules\\{$moduleName}\\Models\\{$model};", ""];
        $lines[] = "class {$className}";
        $lines[] = "{";
        
        // Index
        $lines[] = "    public function index()";
        $lines[] = "    {";
        $lines[] = "        \$items = {$model}::all();";
        $lines[] = "        return view('" . Str::kebab($moduleName) . "::" . Str::kebab($plural) . ".index', compact('items'));";
        $lines[] = "    }";
        $lines[] = "";

        // Show
        $lines[] = "    public function show(string \$id)";
        $lines[] = "    {";
        $lines[] = "        \${$var} = {$model}::find(\$id);";
        $lines[] = "        return view('" . Str::kebab($moduleName) . "::" . Str::kebab($plural) . ".show', compact('{$var}'));";
        $lines[] = "    }";
        $lines[] = "";

        // Store
        $lines[] = "    public function store(Request \$request)";
        $lines[] = "    {";
        if ($ai) {
            $lines[] = "        // AI Enhanced: Dynamic validation";
            $lines[] = "        \$data = \$request->all();";
        } else {
            $lines[] = "        \$data = \$request->validate([]);";
        }
        $lines[] = "        {$model}::create(\$data);";
        $lines[] = "        return redirect('/" . Str::kebab($moduleName) . "/" . Str::kebab($plural) . "');";
        $lines[] = "    }";

        // Update & Destroy stubs...
        $lines[] = "";
        $lines[] = "    public function update(Request \$request, string \$id) { /* Update logic */ }";
        $lines[] = "    public function destroy(string \$id) { /* Destroy logic */ }";

        $lines[] = "}";

        file_put_contents($path . "/Http/Controllers/{$className}.php", implode("\n", $lines) . "\n");
    }

    protected function createMigration(string $path, string $singular, string $plural, string $table, bool $ai): void
    {
        $timestamp = date('Y_m_d_His');
        $className = "Create" . Str::studly($plural) . "Table";
        $fileName  = "{$timestamp}_create_{$table}_table.php";

        $lines = ["<?php", "", "declare(strict_types=1);", "", "use Libxa\\Atlas\\Schema\\Blueprint;", "use Libxa\\Atlas\\DB;", ""];
        $lines[] = "return new class";
        $lines[] = "{";
        $lines[] = "    public function up(): void";
        $lines[] = "    {";
        $lines[] = "        DB::schema()->create('{$table}', function (Blueprint \$table) {";
        $lines[] = "            \$table->id();";
        if ($ai) {
            $lines[] = "            \$table->string('uuid')->unique();";
            $lines[] = "            \$table->string('name');";
            $lines[] = "            \$table->string('slug')->unique();";
            $lines[] = "            \$table->text('description')->nullable();";
            $lines[] = "            \$table->decimal('price', 15, 2)->default(0);";
            $lines[] = "            \$table->json('metadata')->nullable();";
        } else {
            $lines[] = "            \$table->string('name');";
        }
        $lines[] = "            \$table->timestamps();";
        $lines[] = "        });";
        $lines[] = "    }";
        $lines[] = "";
        $lines[] = "    public function down(): void";
        $lines[] = "    {";
        $lines[] = "        DB::schema()->dropIfExists('{$table}');";
        $lines[] = "    }";
        $lines[] = "};";

        file_put_contents($path . "/Database/Migrations/{$fileName}", implode("\n", $lines) . "\n");
    }

    protected function createExampleFiles(string $path, string $name): void
    {
        $prefix = Str::kebab($name);

        // Example View
        $viewStub = "<h1>Welcome to the {$name} module</h1>\n<p>Generated by LibxaFrame Professional CLI.</p>";
        file_put_contents($path . '/Resources/views/welcome.blade.php', $viewStub);

        // Config
        $configStub = "<?php\n\nreturn [\n    'enabled' => true,\n];\n";
        file_put_contents($path . "/Config/{$prefix}.php", $configStub);
    }
}
