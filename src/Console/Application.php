<?php

declare(strict_types=1);

namespace Libxa\Console;

use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application as LibxaApplication;

/**
 * Libxa Console Application
 *
 * Discovers and registers commands from the framework and app.
 */
class Application extends SymfonyApplication
{
    protected bool $discovered = false;
    
    // ANSI colour codes
    private const C_RESET  = "\033[0m";
    private const C_BOLD   = "\033[1m";
    private const C_DIM    = "\033[2m";
    private const C_INDIGO = "\033[38;2;99;102;241m";
    private const C_PURPLE = "\033[38;2;192;132;252m";
    private const C_MUTED  = "\033[38;2;148;163;184m";

    public function __construct(protected LibxaApplication $app)
    {
        parent::__construct('LibxaFrame', LibxaApplication::VERSION);
    }

    /**
     * Override run to ensure commands are discovered before running
     */
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        if (! $this->discovered) {
            $this->discoverCommands();
            $this->discovered = true;
        }
        return parent::run($input, $output);
    }

    /**
     * Called by Symfony's `list` command to show the app header.
     * This replaces the default "LibxaFrame 1.0.0" plain text with the logo.
     */
    public function getHelp(): string
    {
        return $this->renderBanner();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Visual helpers
    // ─────────────────────────────────────────────────────────────────

    public function renderLogo(): string
    {
        $I = self::C_INDIGO;
        $P = self::C_PURPLE;
        $R = self::C_RESET;

        return implode(PHP_EOL, [
            "  {$I}██╗     ██╗{$P}██████╗ ██╗  ██╗ █████╗ {$R}",
            "  {$I}██║     ██║{$P}██╔══██╗╚██╗██╔╝██╔══██╗{$R}",
            "  {$I}██║     ██║{$P}██████╔╝ ╚███╔╝ ███████║{$R}",
            "  {$I}██║     ██║{$P}██╔══██╗ ██╔██╗ ██╔══██║{$R}",
            "  {$I}███████╗██║{$P}██████╔╝██╔╝ ██╗██║  ██║{$R}",
            "  {$I}╚══════╝╚═╝{$P}╚═════╝ ╚═╝  ╚═╝╚═╝  ╚═╝{$R}",
        ]);
    }

    private function renderBanner(): string
    {
        $I = self::C_INDIGO;
        $M = self::C_MUTED;
        $D = self::C_DIM;
        $B = self::C_BOLD;
        $P = self::C_PURPLE;
        $R = self::C_RESET;

        $version = LibxaApplication::VERSION;
        $env     = $this->app->env('APP_ENV', 'local');

        return implode(PHP_EOL, [
            '',
            $this->renderLogo(),
            '',
            "  {$D}{$M}The modern PHP framework for the next generation   {$B}v{$version}{$R}  {$I}[{$env}]{$R}",
            "  {$D}{$M}Author: {$R}{$B}{$P}Voukeng Dongmo Franky Steve{$R}  {$D}{$M}·  {$R}{$I}libxa@vyloxi.com{$R}",
            '',
            "  {$I}┌─────────────────────────────────────────────────────────────┐{$R}",
            "  {$I}│{$R}  {$M}Run {$B}{$I}php Libxa serve{$R}{$M}            → start the dev server       {$I}│{$R}",
            "  {$I}│{$R}  {$M}Run {$B}{$I}php Libxa ws:serve{$R}{$M}         → start websocket server     {$I}│{$R}",
            "  {$I}│{$R}  {$M}Run {$B}{$I}php Libxa serve --host --port{$R}{$M} → custom host & port      {$I}│{$R}",
            "  {$I}│{$R}  {$M}Run {$B}{$I}php Libxa migrate{$R}{$M}          → run migrations             {$I}│{$R}",
            "  {$I}│{$R}  {$M}Run {$B}{$I}php Libxa make:controller Name{$R}{$M} → scaffold controller   {$I}│{$R}",
            "  {$I}└─────────────────────────────────────────────────────────────┘{$R}",
            '',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Command Discovery
    // ─────────────────────────────────────────────────────────────────

    public function discoverCommands(): void
    {
        $this->addCommands([
            new Commands\ApiInstallCommand($this->app),
            new Commands\MakeControllerCommand($this->app),
            new Commands\MakeAdminWidgetCommand($this->app),
            new Commands\MakeAdminPageCommand($this->app),
            new Commands\MakeModuleCommand($this->app),
            new Commands\MigrateCommand($this->app),
            new Commands\MigrateStatusCommand($this->app),
            new Commands\MigrateRollbackCommand($this->app),
            new Commands\MigrateRefreshCommand($this->app),
            new Commands\ServeCommand($this->app),
            new Commands\StorageLinkCommand($this->app),
            new Commands\QueueTableCommand($this->app),
            new Commands\QueueWorkCommand($this->app),
            new Commands\QueueListenCommand($this->app),
            new Commands\QueueRestartCommand($this->app),
            new Commands\MakeRequestCommand($this->app),
            new Commands\MakeModelCommand($this->app),
            new Commands\MakeMigrationCommand($this->app),
            new Commands\MakeSeederCommand($this->app),
            new Commands\MakeMiddlewareCommand($this->app),
            new Commands\MakeCommandCommand($this->app),
            new Commands\MakeProviderCommand($this->app),
            new Commands\MakeEventCommand($this->app),
            new Commands\MakeListenerCommand($this->app),
            new Commands\Db\SeedCommand($this->app),
            new Commands\Cache\ClearCommand($this->app),
            new Commands\Config\ClearCommand($this->app),
            new Commands\Config\CacheCommand($this->app),
            new Commands\Route\ClearCommand($this->app),
            new Commands\Route\CacheCommand($this->app),
            new Commands\View\ClearCommand($this->app),
            new Commands\EnvCommand($this->app),
            new Commands\TestCommand($this->app),
            new Commands\Package\DiscoverCommand($this->app),
            new Commands\Package\AddCommand($this->app),
            new Commands\Vendor\PublishCommand($this->app),
            new Commands\Module\ExtractCommand($this->app),
            new Commands\Frontend\AddCommand($this->app),
            new Commands\Npm\AddCommand($this->app),
            new Commands\Npm\RemoveCommand($this->app),
            new Commands\Ui\TailwindCommand($this->app),
            new Commands\KeyGenerateCommand($this->app),
        ]);

        // Auto-scan app/Console/Commands
        $appCommandsDir = $this->app->appPath('Console/Commands');
        if (is_dir($appCommandsDir)) {
            $this->addFromDirectory($appCommandsDir);
        }
        
        // Load package commands from manifest (packages/ directory)
        $packages = (new \Libxa\Module\ModuleManifestManager($this->app, 'packages'))->load();
        foreach ($packages as $package) {
            $path = $package['path'] ?? null;
            if ($path && is_dir($path . '/src/Console/Commands')) {
                $this->addFromDirectory($path . '/src/Console/Commands');
            }
        }

        // Load commands from Composer-installed packages (vendor/ directory)
        $vendorDir = $this->app->basePath() . '/vendor';
        if (is_dir($vendorDir)) {
            // Scan libxa and libxaframe packages directly
            $vendorPackages = ['libxa', 'libxaframe'];
            foreach ($vendorPackages as $vendor) {
                $vendorPath = $vendorDir . '/' . $vendor;
                if (is_dir($vendorPath)) {
                    $packageDirs = glob($vendorPath . '/*', GLOB_ONLYDIR);
                    foreach ($packageDirs as $packagePath) {
                        $commandsPath = $packagePath . '/src/Console/Commands';
                        if (is_dir($commandsPath)) {
                            $this->addFromDirectory($commandsPath);
                        }
                    }
                }
            }
        }
    }

    /**
     * Add all commands from a directory.
     */
    public function addFromDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*.php');
        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file);
            if ($className && class_exists($className)) {
                $command = new $className($this->app);
                $this->add($command);
            }
        }
    }

    /**
     * Get the fully qualified class name from a file.
     */
    private function getClassNameFromFile(string $file): ?string
    {
        $content = file_get_contents($file);
        
        // Extract namespace
        if (preg_match('/namespace\s+([\w\\\\]+);/i', $content, $matches)) {
            $namespace = $matches[1];
        } else {
            return null;
        }

        // Extract class name
        if (preg_match('/class\s+(\w+)/i', $content, $matches)) {
            $className = $matches[1];
        } else {
            return null;
        }

        return $namespace . '\\' . $className;
    }
}
