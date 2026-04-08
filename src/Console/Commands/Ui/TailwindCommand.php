<?php

declare(strict_types=1);

namespace Libxa\Console\Commands\Ui;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Libxa\Foundation\Application;

/**
 * Tailwind Install Command (v4)
 *
 * Scaffolds a standalone Tailwind CSS v4 setup for LibxaFrame.
 */
class TailwindCommand extends Command
{
    protected static $defaultName = 'ui:tailwind';

    public function __construct(protected Application $Libxa)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ui:tailwind')
             ->setDescription('Scaffold a standalone Tailwind CSS v4 setup for Blade');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title("Scaffolding Standalone Tailwind CSS v4");

        // 1. Install Dependencies
        $io->section("1. Installing NPM Dependencies (v4)...");
        $npmAdd = $this->getApplication()->find('npm:add');
        
        $deps = ['tailwindcss@latest', '@tailwindcss/vite'];
        $inputDev = new ArrayInput([
            'packages' => $deps,
            '--dev'    => true,
        ]);
        $npmAdd->run($inputDev, $output);

        // 2. Configure Vite
        $io->section("2. Configuring Vite Plugin...");
        $this->configureVite($io);

        // 3. Setup CSS Entry Point
        $io->section("3. Setting up CSS Entry Point (Modern Syntax)...");
        $this->setupCssEntry($io);

        // 4. Wire up Vite in Layout
        $io->section("4. Wiring up Vite in Layout...");
        $this->wireUpVite($io);

        $io->success("Tailwind CSS v4 has been scaffolded successfully!");
        $io->note([
            "1. Start the dev server: npm run dev",
            "2. Access your app and start using Tailwind classes in your Blade views.",
            "3. Tailwind v4 uses a zero-config CSS-first approach. Custom colors are in app.css."
        ]);

        return Command::SUCCESS;
    }

    protected function configureVite(SymfonyStyle $io): void
    {
        $path = $this->Libxa->basePath('vite.config.js');
        if (! file_exists($path)) return;

        $content = file_get_contents($path);
        
        if (! str_contains($content, '@tailwindcss/vite')) {
            // Add import
            $content = str_replace(
                "import { defineConfig } from 'vite'",
                "import { defineConfig } from 'vite'\nimport tailwindcss from '@tailwindcss/vite'",
                $content
            );
            
            // Add plugin
            $content = str_replace(
                "plugins: [",
                "plugins: [\n        tailwindcss(),",
                $content
            );
            
            file_put_contents($path, $content);
            $io->text("Updated: <info>vite.config.js</info> (Added v4 plugin)");
        }
    }

    protected function setupCssEntry(SymfonyStyle $io): void
    {
        $cssDir = $this->Libxa->basePath('src/resources/css');
        if (! is_dir($cssDir)) {
            mkdir($cssDir, 0755, true);
        }

        $path = $cssDir . '/app.css';
        $content = <<<CSS
@import "tailwindcss";

@theme {
    /* Professional Indigo/Slate Palette (LibxaFrame Default) */
    --color-primary-50: #f5f3ff;
    --color-primary-100: #ede9fe;
    --color-primary-200: #ddd6fe;
    --color-primary-300: #c4b5fd;
    --color-primary-400: #a78bfa;
    --color-primary-500: #8b5cf6;
    --color-primary-600: #7c3aed;
    --color-primary-700: #6d28d9;
    --color-primary-800: #5b21b6;
    --color-primary-900: #4c1d95;
    --color-primary-950: #2e1065;
}

/* Custom LibxaFrame Styles */
@layer base {
    body {
        @apply bg-slate-950 text-slate-50 antialiased;
    }
}
CSS;

        file_put_contents($path, $content);
        $io->text("Updated: <info>src/resources/css/app.css</info>");

        // Ensure JS imports CSS
        $jsPath = $this->Libxa->basePath('src/resources/js/app.js');
        if (file_exists($jsPath)) {
            $jsContent = file_get_contents($jsPath);
            if (! str_contains($jsContent, "import '../css/app.css'")) {
                $jsContent = "import '../css/app.css';\n" . $jsContent;
                file_put_contents($jsPath, $jsContent);
            }
        }
    }

    protected function wireUpVite(SymfonyStyle $io): void
    {
        $layoutPath = $this->Libxa->basePath('src/resources/views/layouts/app.blade.php');
        if (! file_exists($layoutPath)) return;

        $content = file_get_contents($layoutPath);
        if (! str_contains($content, '@vite')) {
            $vite = "    @vite(['src/resources/js/app.js', 'src/resources/css/app.css'])\n";
            $content = str_replace('</head>', $vite . '</head>', $content);
            file_put_contents($layoutPath, $content);
            $io->text("Injected: <info>@vite</info> directive into layout");
        }
    }
}
