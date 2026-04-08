<?php

declare(strict_types=1);

namespace Libxa\Console\Commands\Package;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Libxa\Foundation\Application;
use Libxa\Module\ModuleManifestManager;

/**
 * Package Discover Command
 * 
 * Scans the vendor directory for Libxa-compatible packages
 * and updates the packages.php manifest.
 */
class DiscoverCommand extends Command
{

    public function __construct(protected Application $Libxa)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('package:discover')
            ->setDescription('Rebuild the package discovery manifest');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('LibxaFrame PackageDiscovery');

        $vendorPath = $this->Libxa->basePath('vendor');
        if (! is_dir($vendorPath)) {
            $io->error('Vendor directory not found. Have you run composer install?');
            return Command::FAILURE;
        }

        $io->text('Scanning vendor directory for Libxa-compatible packages...');

        $packages = [];
        $vendorDirs = array_filter(glob("$vendorPath/*"), 'is_dir');

        foreach ($vendorDirs as $vendorDir) {
            $packageDirs = array_filter(glob("$vendorDir/*"), 'is_dir');
            foreach ($packageDirs as $packageDir) {
                $composerJson = $packageDir . DIRECTORY_SEPARATOR . 'composer.json';
                if (! file_exists($composerJson)) {
                    continue;
                }

                $config = json_decode(file_get_contents($composerJson), true);
                if (isset($config['extra']['Libxa'])) {
                    $packageName = $config['name'] ?? basename($vendorDir) . '/' . basename($packageDir);
                    $io->text("Discovered package: <info>{$packageName}</info>");
                    
                    $packages[$packageName] = $config['extra']['Libxa'];
                    $packages[$packageName]['path'] = $packageDir;
                }
            }
        }

        (new ModuleManifestManager($this->Libxa, 'packages'))->save($packages);

        $io->success('Package manifest rebuilt successfully.');

        return Command::SUCCESS;
    }
}
