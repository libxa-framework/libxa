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

    public function __construct(protected LibxaApplication $Libxa)
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
        $env     = $this->Libxa->env('APP_ENV', 'local');

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
            new Commands\ApiInstallCommand($this->Libxa),
            new Commands\MakeControllerCommand($this->Libxa),
            new Commands\MakeAdminWidgetCommand($this->Libxa),
            new Commands\MakeAdminPageCommand($this->Libxa),
            new Commands\MakeModuleCommand($this->Libxa),
            new Commands\MigrateCommand($this->Libxa),
            new Commands\MigrateStatusCommand($this->Libxa),
            new Commands\ServeCommand($this->Libxa),
            new Commands\WsServeCommand($this->Libxa),
            new Commands\StorageLinkCommand($this->Libxa),
            new Commands\QueueTableCommand($this->Libxa),
            new Commands\QueueWorkCommand($this->Libxa),
            new Commands\WsInstallCommand($this->Libxa),
            new Commands\MakeRequestCommand($this->Libxa),
            new Commands\Package\DiscoverCommand($this->Libxa),
            new Commands\Package\AddCommand($this->Libxa),
            new Commands\Vendor\PublishCommand($this->Libxa),
            new Commands\Module\ExtractCommand($this->Libxa),
            new Commands\Frontend\AddCommand($this->Libxa),
            new Commands\Npm\AddCommand($this->Libxa),
            new Commands\Npm\RemoveCommand($this->Libxa),
            new Commands\Ui\TailwindCommand($this->Libxa),
            new Commands\KeyGenerateCommand($this->Libxa),
        ]);

        // Auto-scan app/Console/Commands
        $appCommandsDir = $this->Libxa->appPath('Console/Commands');
        if (is_dir($appCommandsDir)) {
            $this->addFromDirectory($appCommandsDir);
        }
        
        // Load package commands from manifest (packages/ directory)
        $packages = (new \Libxa\Module\ModuleManifestManager($this->Libxa, 'packages'))->load();
        foreach ($packages as $package) {
            $path = $package['path'] ?? null;
            if ($path && is_dir($path . '/src/Console/Commands')) {
                $this->addFromDirectory($path . '/src/Console/Commands');
            }
        }

        // Load commands from Composer-installed packages (vendor/ directory)
        $vendorDir = $this->Libxa->basePath() . '/vendor';
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
                $command = new $className($this->Libxa);
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
