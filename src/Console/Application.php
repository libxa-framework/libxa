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
        $this->discoverCommands();
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

    protected function discoverCommands(): void
    {
        $this->addCommands([
            new Commands\ApiInstallCommand($this->Libxa),
            new Commands\MakeControllerCommand($this->Libxa),
            new Commands\MakeModuleCommand($this->Libxa),
            new Commands\MigrateCommand($this->Libxa),
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
            // Future: dynamic command discovery
        }
    }
}
