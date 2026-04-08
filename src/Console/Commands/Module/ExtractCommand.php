<?php

declare(strict_types=1);

namespace Libxa\Console\Commands\Module;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Libxa\Foundation\Application;
use Libxa\Support\Str;
use Libxa\Module\NamespaceMigrator;

/**
 * Module Extract Command
 * 
 * "Graduates" a local module in src/app/Modules to a standalone package in packages/.
 * - AST-based namespace renaming
 * - Generation of composer.json
 * - Update root composer.json with path repository
 * - Cleanup local module
 */
class ExtractCommand extends Command
{

    public function __construct(protected Application $Libxa)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('module:extract')
            ->setDescription('Graduate a local module to a standalone package')
            ->addArgument('module', InputArgument::REQUIRED, 'The module name')
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'The new namespace (e.g. Acme\Billing)')
            ->addOption('vendor', null, InputOption::VALUE_REQUIRED, 'The vendor name for composer (e.g. acme)')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output directory (relative to project root)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $module = Str::studly($input->getArgument('module'));
        $targetNamespace = $input->getOption('namespace') ?? "Libxa\\" . $module;
        $vendor = $input->getOption('vendor') ?? "Libxa";
        
        $sourcePath = $this->Libxa->modulesPath($module);
        
        if (! is_dir($sourcePath)) {
            $io->error("Module [{$module}] not found in src/app/Modules.");
            return Command::FAILURE;
        }

        $io->title("Extracting Module: {$module}");

        $slug = Str::kebab($module);
        $outputPath = $input->getOption('output') ?? "packages/{$slug}";
        $fullOutputPath = $this->Libxa->basePath($outputPath);

        if (is_dir($fullOutputPath)) {
            $io->error("Target path [{$outputPath}] already exists!");
            return Command::FAILURE;
        }

        // 1. Create target structure
        $io->text("Creating package structure at <info>{$outputPath}</info>...");
        mkdir($fullOutputPath, 0755, true);
        mkdir($fullOutputPath . '/src', 0755, true);
        
        // 2. Copy content
        $io->text("Copying module contents...");
        $this->copyDirectory($sourcePath, $fullOutputPath . '/src');

        // 3. Namespace Migration
        $io->text("Performing AST-based namespace migration...");
        $io->text("<info>App\\Modules\\{$module}</info> → <info>{$targetNamespace}</info>");
        
        (new NamespaceMigrator())->migrateDirectory(
            $fullOutputPath . '/src',
            "App\\Modules\\{$module}",
            $targetNamespace
        );

        // 4. Generate composer.json
        $io->text("Generating <info>composer.json</info>...");
        $this->generateComposerJson($fullOutputPath, $vendor, $slug, $targetNamespace, $module);

        // 5. Update root composer.json
        $io->text("Updating root composer.json with path repository...");
        $this->updateRootComposer($vendor, $slug);

        // 6. Cleanup local module placeholder
        $io->text("Cleaning up local module...");
        $this->cleanupLocalModule($sourcePath, $module, $targetNamespace);

        $io->success("Module [{$module}] graduated to package [{$vendor}/{$slug}] successfully.");
        $io->warning("Run 'composer update' to register the new package symlink.");

        return Command::SUCCESS;
    }

    protected function generateComposerJson(string $path, string $vendor, string $slug, string $namespace, string $module): void
    {
        $packageName = "{$vendor}/{$slug}";
        $provider = "{$namespace}\\{$module}ServiceProvider";

        $config = [
            'name' => $packageName,
            'description' => "LibxaFrame package extracted from module {$module}",
            'type' => 'Libxa-module',
            'require' => [
                'php' => '^8.3',
                'Libxa/framework' => '*'
            ],
            'autoload' => [
                'psr-4' => [
                    $namespace . '\\' => 'src/'
                ]
            ],
            'extra' => [
                'Libxa' => [
                    'providers' => [
                        $provider
                    ]
                ]
            ]
        ];

        file_put_contents(
            $path . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    protected function updateRootComposer(string $vendor, string $slug): void
    {
        $rootFile = $this->Libxa->basePath('composer.json');
        $rootConfig = json_decode(file_get_contents($rootFile), true);
        $packageName = "{$vendor}/{$slug}";

        // Add to require
        $rootConfig['require'][$packageName] = '@dev';

        // Add to path repository if not exists
        $hasRepo = false;
        foreach (($rootConfig['repositories'] ?? []) as $repo) {
            if ($repo['type'] === 'path' && (str_contains($repo['url'], 'packages/*') || str_contains($repo['url'], "./packages/{$slug}"))) {
                $hasRepo = true;
                break;
            }
        }

        if (! $hasRepo) {
            $rootConfig['repositories'][] = [
                'type' => 'path',
                'url' => "./packages/{$slug}",
                'options' => ['symlink' => true]
            ];
        }

        file_put_contents(
            $rootFile,
            json_encode($rootConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    protected function cleanupLocalModule(string $path, string $module, string $namespace): void
    {
        // Replace module folder with a stub or just delete (we'll delete for cleanliness since composer now handles it)
        $this->deleteDirectory($path);
    }

    protected function copyDirectory(string $src, string $dst): void
    {
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->copyDirectory($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    protected function deleteDirectory(string $dir): bool
    {
        if (! file_exists($dir)) return true;
        if (! is_dir($dir)) return unlink($dir);

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;
            if (! $this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
        }

        return rmdir($dir);
    }
}
