<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Libxa\Reactive\WsServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Libxa\Foundation\Application;

/**
 * CLI command to start the LibxaFrame WebSocket server.
 */
class WsServeCommand extends Command
{
    protected static $defaultName = 'ws:serve';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('ws:serve')
            ->setDescription('Start the LibxaFrame WebSocket server')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'The host to bind to', env('WS_HOST', '0.0.0.0'))
            ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'The port to listen on', (int) env('WS_PORT', 8081))
            ->addOption('workers', null, InputOption::VALUE_OPTIONAL, 'Number of worker processes', (int) env('WS_WORKERS', 4));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $host = $input->getOption('host');
        $port = (int) $input->getOption('port');
        $workers = (int) $input->getOption('workers');

        $io->title('LibxaFrame WebSocket Server');
        $io->info("Starting server on {$host}:{$port} with {$workers} workers...");
        
        $server = new WsServer($host, $port, $workers, $this->app);
        $server->start();

        return Command::SUCCESS;
    }
}
