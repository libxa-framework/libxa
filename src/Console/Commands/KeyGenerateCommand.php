<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Libxa\Foundation\Application;

/**
 * Key Generation Command
 *
 * Scaffolds or refreshes the APP_KEY for LibxaFrame.
 */
class KeyGenerateCommand extends Command
{
    protected static $defaultName = 'key:generate';

    public function __construct(protected Application $Libxa)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('key:generate')
             ->setDescription('Set the application key')
             ->addOption('show', null, InputOption::VALUE_NONE, 'Display the key instead of modifying files')
             ->addOption('force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $key = $this->generateRandomKey();

        if ($input->getOption('show')) {
            $io->text("<comment>{$key}</comment>");
            return Command::SUCCESS;
        }

        if (! $this->setKeyInEnvironmentFile($key)) {
            $io->error('Failed to set the application key. Ensure your .env file exists and is writable.');
            return Command::FAILURE;
        }

        $io->success("Application key [{$key}] set successfully.");

        return Command::SUCCESS;
    }

    /**
     * Generate a random key for the application.
     */
    protected function generateRandomKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    /**
     * Set the application key in the environment file.
     */
    protected function setKeyInEnvironmentFile(string $key): bool
    {
        $path = $this->Libxa->basePath('.env');

        if (! file_exists($path)) {
            return false;
        }

        $content = file_get_contents($path);

        if (preg_match('/^APP_KEY=.*/m', $content)) {
            $content = preg_replace('/^APP_KEY=.*/m', "APP_KEY={$key}", $content);
        } else {
            $content .= "\nAPP_KEY={$key}";
        }

        return (bool) file_put_contents($path, $content);
    }
}
