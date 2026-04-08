<?php

declare(strict_types=1);

namespace Libxa\Console\Commands\Frontend;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Libxa\Foundation\Application;

/**
 * Frontend Add Command
 * 
 * Automates:
 * 1. npm install <package>
 * 2. Scaffolding src/resources/js/app.js & css/app.css
 * 3. Wiring up Vite in app.blade.php
 */
class AddCommand extends Command
{

    public function __construct(protected Application $Libxa)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('frontend:add')
            ->setDescription('Install an NPM package and wire up Vite')
            ->addArgument('package', InputArgument::REQUIRED, 'The NPM package name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $package = $input->getArgument('package');

        $io->title("Adding Frontend Package: {$package}");

        // 1. Install NPM Package
        $io->text("Running <info>npm install {$package}</info>...");
        $command = "npm install {$package}";
        passthru($command, $exitCode);

        if ($exitCode !== 0) {
            $io->error("Failed to install {$package} via NPM.");
            return Command::FAILURE;
        }

        // 2. Ensure entry points exist
        $this->ensureEntryPointsExist($io);

        // 3. Wire up the package in app.js
        $this->wireUpPackage($package, $io);

        // 4. Wire up Vite in Blade
        $this->wireUpViteInBlade($io);

        $io->success("Frontend package {$package} installed and wired up successfully!");
        $io->note("Run 'npm run dev' to start the Vite development server.");

        return Command::SUCCESS;
    }

    protected function ensureEntryPointsExist(SymfonyStyle $io): void
    {
        $jsDir = $this->Libxa->basePath('src/resources/js');
        $cssDir = $this->Libxa->basePath('src/resources/css');

        if (! is_dir($jsDir)) {
            mkdir($jsDir, 0755, true);
            $io->text("Created directory: <info>src/resources/js</info>");
        }

        if (! is_dir($cssDir)) {
            mkdir($cssDir, 0755, true);
            $io->text("Created directory: <info>src/resources/css</info>");
        }

        $appJs = $jsDir . '/app.js';
        $appCss = $cssDir . '/app.css';

        if (! file_exists($appJs)) {
            file_put_contents($appJs, "// LibxaFrame JS Entry Point\nimport '../css/app.css';\n");
            $io->text("Created entry point: <info>src/resources/js/app.js</info>");
        }

        if (! file_exists($appCss)) {
            file_put_contents($appCss, "/* LibxaFrame CSS Entry Point */\n");
            $io->text("Created entry point: <info>src/resources/css/app.css</info>");
        }
    }

    protected function wireUpPackage(string $package, SymfonyStyle $io): void
    {
        $appJs = $this->Libxa->basePath('src/resources/js/app.js');
        $content = file_get_contents($appJs);

        if (! str_contains($content, "import '{$package}'")) {
            $content .= "\nimport '{$package}';";
            file_put_contents($appJs, $content);
            $io->text("Added import to <info>app.js</info>");
        }
    }

    protected function wireUpViteInBlade(SymfonyStyle $io): void
    {
        $layoutPath = $this->Libxa->basePath('src/resources/views/layouts/app.blade.php');
        
        if (! file_exists($layoutPath)) {
            return;
        }

        $content = file_get_contents($layoutPath);

        if (! str_contains($content, '@vite')) {
            $viteDirective = "    @vite(['src/resources/js/app.js', 'src/resources/css/app.css'])\n";
            $content = str_replace('</head>', $viteDirective . '</head>', $content);
            file_put_contents($layoutPath, $content);
            $io->text("Injected @vite directive into <info>app.blade.php</info>");
        }
    }
}
