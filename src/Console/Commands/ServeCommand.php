<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

/**
 * Serve Command
 *
 * Starts the PHP built-in development server.
 *
 * Usage:
 *   php Libxa serve
 *   php Libxa serve --host=0.0.0.0 --port=9000
 */
class ServeCommand extends Command
{
    protected static $defaultName = 'serve';

    // ANSI colour helpers
    private const C_RESET   = "\033[0m";
    private const C_BOLD    = "\033[1m";
    private const C_DIM     = "\033[2m";
    private const C_INDIGO  = "\033[38;2;99;102;241m";   // #6366f1
    private const C_PURPLE  = "\033[38;2;192;132;252m";  // #c084fc
    private const C_WHITE   = "\033[38;2;248;250;252m";  // #f8fafc
    private const C_MUTED   = "\033[38;2;148;163;184m";  // #94a3b8
    private const C_GREEN   = "\033[38;2;34;197;94m";    // #22c55e
    private const C_CYAN    = "\033[38;2;34;211;238m";   // #22d3ee
    private const C_BG_DARK = "\033[48;2;15;23;42m";     // #0f172a

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('serve')
            ->setDescription('Start the LibxaFrame development server')
            ->setHelp('Starts the PHP built-in HTTP server pointing at src/public.')
            ->addOption(
                'host',
                null,
                InputOption::VALUE_OPTIONAL,
                'The host address to serve on',
                $this->defaultHost()
            )
            ->addOption(
                'port',
                null,
                InputOption::VALUE_OPTIONAL,
                'The port to serve on',
                $this->defaultPort()
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host       = $input->getOption('host') ?: $this->defaultHost();
        $port       = (int) ($input->getOption('port') ?: $this->defaultPort());
        $docRoot    = $this->app->publicPath();
        $serverAddr = "$host:$port";
        $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
        $env        = $this->app->env('APP_ENV', 'local');
        $appName    = $this->app->env('APP_NAME', 'LibxaFrame App');

        $this->printBanner($output, $serverAddr, $docRoot, $phpVersion, $env, $appName);

        // Build the PHP built-in server command
        $phpBinary = PHP_BINARY;
        $command   = sprintf(
            '%s -S %s -t %s',
            escapeshellarg($phpBinary),
            $serverAddr,
            escapeshellarg($docRoot)
        );

        // Use router script if present
        $routerScript = $this->app->publicPath('router.php');
        if (file_exists($routerScript)) {
            $command .= ' ' . escapeshellarg($routerScript);
        }

        passthru($command, $exitCode);

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Banner
    // ─────────────────────────────────────────────────────────────────

    private function printBanner(
        OutputInterface $output,
        string $serverAddr,
        string $docRoot,
        string $phpVersion,
        string $env,
        string $appName,
    ): void {
        $I = self::C_INDIGO;
        $P = self::C_PURPLE;
        $W = self::C_WHITE;
        $M = self::C_MUTED;
        $G = self::C_GREEN;
        $C = self::C_CYAN;
        $B = self::C_BOLD;
        $D = self::C_DIM;
        $R = self::C_RESET;

        // ── ASCII Logo (gradient indigo → purple) ──────────────────
        $logo = [
            "{$I}██╗     ██╗{$P}██████╗ ██╗  ██╗ █████╗ {$R}",
            "{$I}██║     ██║{$P}██╔══██╗╚██╗██╔╝██╔══██╗{$R}",
            "{$I}██║     ██║{$P}██████╔╝ ╚███╔╝ ███████║{$R}",
            "{$I}██║     ██║{$P}██╔══██╗ ██╔██╗ ██╔══██║{$R}",
            "{$I}███████╗██║{$P}██████╔╝██╔╝ ██╗██║  ██║{$R}",
            "{$I}╚══════╝╚═╝{$P}╚═════╝ ╚═╝  ╚═╝╚═╝  ╚═╝{$R}",
        ];

        $output->writeln('');
        foreach ($logo as $line) {
            $output->writeln("  $line");
        }

        // ── Tagline & Author ──────────────────────────────────────
        $output->writeln("  {$D}{$M}The modern PHP framework for the next generation.{$R}");
        $output->writeln("  {$D}{$M}Author: {$R}{$B}{$P}Voukeng Dongmo Franky Steve{$R}  {$D}{$M}·  {$R}{$I}libxa@vyloxi.com{$R}");
        $output->writeln('');

        // ── Divider ───────────────────────────────────────────────
        $divider = "{$I}  ┌──────────────────────────────────────────────────────────────┐{$R}";
        $output->writeln($divider);

        // ── Info rows ─────────────────────────────────────────────
        $this->row($output, '  │', '🚀', 'Server',   "{$G}{$B}http://{$serverAddr}{$R}",                  $I);
        $this->row($output, '  │', '📂', 'Root',     "{$C}{$docRoot}{$R}",                                $I);
        $this->row($output, '  │', '⚡', 'PHP',      "{$W}{$phpVersion}{$R}",                             $I);
        $this->row($output, '  │', '🌍', 'Env',      "{$W}{$env}{$R}",                                   $I);
        $this->row($output, '  │', '📦', 'App',      "{$W}{$appName}{$R}",                               $I);

        $output->writeln("{$I}  └──────────────────────────────────────────────────────────────┘{$R}");
        $output->writeln('');
        $output->writeln("  {$M}  Press {$W}{$B}Ctrl+C{$R}{$M} to stop the server.{$R}");
        $output->writeln('');
        $output->writeln("  {$D}{$M}──────────────────────── Server Log ─────────────────────────────{$R}");
        $output->writeln('');
    }

    private function row(
        OutputInterface $output,
        string $prefix,
        string $icon,
        string $label,
        string $value,
        string $accent,
    ): void {
        $M = self::C_MUTED;
        $R = self::C_RESET;
        $labelPad = str_pad($label, 8);
        $output->writeln("{$accent}{$prefix}{$R}  {$icon}  {$M}{$labelPad}{$R}  {$value}");
    }

    // ─────────────────────────────────────────────────────────────────

    protected function defaultHost(): string
    {
        return $this->app->env('SERVER_HOST', '127.0.0.1');
    }

    protected function defaultPort(): int
    {
        return (int) $this->app->env('SERVER_PORT', 8000);
    }
}
