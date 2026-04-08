<?php

declare(strict_types=1);

namespace Libxa\Console\Commands\Vendor;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Libxa\Foundation\Application;
use Libxa\Container\PublishableRegistry;

/**
 * Vendor Publish Command
 * 
 * Copies publishable assets from packages to the application.
 */
class PublishCommand extends Command
{

    public function __construct(protected Application $Libxa)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('vendor:publish')
            ->setDescription('Publish any publishable assets from vendor packages')
            ->addOption('tag', 't', InputOption::VALUE_REQUIRED, 'The tag to publish')
            ->addOption('provider', 'p', InputOption::VALUE_REQUIRED, 'The service provider class to publish from')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tag = $input->getOption('tag');
        $provider = $input->getOption('provider');
        $force = $input->getOption('force');

        $publishables = PublishableRegistry::all();

        if (empty($publishables)) {
            $io->warning('No publishable assets found.');
            return Command::SUCCESS;
        }

        foreach ($publishables as $providerClass => $groups) {
            if ($provider && $providerClass !== $provider) {
                continue;
            }

            foreach ($groups as $groupName => $files) {
                if ($tag && $groupName !== $tag) {
                    continue;
                }

                $io->section("Publishing from [{$providerClass}] (Group: {$groupName})");

                foreach ($files as $from => $to) {
                    $this->publishFile($from, $to, $force, $io);
                }
            }
        }

        $io->success('Publishing complete.');

        return Command::SUCCESS;
    }

    protected function publishFile(string $from, string $to, bool $force, SymfonyStyle $io): void
    {
        if (! file_exists($from)) {
            $io->error("Source file not found: {$from}");
            return;
        }

        if (file_exists($to) && ! $force) {
            $io->note("File already exists: {$to} (skip)");
            return;
        }

        $dir = dirname($to);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (is_dir($from)) {
            $this->copyDirectory($from, $to, $force);
            $io->text("Copied directory: <info>{$from}</info> -> <info>{$to}</info>");
        } else {
            copy($from, $to);
            $io->text("Copied file: <info>{$from}</info> -> <info>{$to}</info>");
        }
    }

    protected function copyDirectory(string $src, string $dst, bool $force): void
    {
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->copyDirectory($src . '/' . $file, $dst . '/' . $file, $force);
                } else {
                    if (! file_exists($dst . '/' . $file) || $force) {
                        copy($src . '/' . $file, $dst . '/' . $file);
                    }
                }
            }
        }
        closedir($dir);
    }
}
