<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;
use Libxa\Queue\QueueManager;

class QueueWorkCommand extends Command
{
    /**
     * Indicates if the worker should quit.
     */
    protected bool $shouldQuit = false;

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('queue:work')
            ->setDescription('Start processing jobs on the queue as a background worker')
            ->addOption('queue', null, InputOption::VALUE_OPTIONAL, 'The name of the queue to work', 'default')
            ->addOption('sleep', null, InputOption::VALUE_OPTIONAL, 'The number of seconds to sleep when no job is available', 3)
            ->addOption('tries', null, InputOption::VALUE_OPTIONAL, 'The number of times to attempt a job before failing', 3);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queue = $input->getOption('queue');
        $sleep = (int) $input->getOption('sleep');
        $tries = (int) $input->getOption('tries');
        
        $output->writeln("<info>Worker started. Listening for jobs on [{$queue}] queue...</info>");

        $this->listenForSignals();

        /** @var QueueManager $queueManager */
        $queueManager = $this->app->make('queue');

        while (! $this->shouldQuit) {
            $connection = $queueManager->connection();
            $job = $connection->pop($queue);

            if ($job) {
                $output->write("Processing job... ");
                
                try {
                    $job->handle();
                    
                    // Delete job from queue on success
                    $connection->deleteReserved($job->getId());
                    
                    $output->writeln("<info>DONE</info>");
                } catch (\Throwable $e) {
                    $output->writeln("<error>FAILED</error>");
                    $output->writeln("<comment>{$e->getMessage()}</comment>");
                    
                    // Handle retries
                    if ($job->attempts() < $tries) {
                        // Release it back with a small delay
                        $connection->release($job->getId(), 60);
                    } else {
                        // Move to failed_jobs or just delete
                        $connection->deleteReserved($job->getId());
                    }
                }
            } else {
                sleep($sleep);
            }
        }

        $output->writeln("<comment>Worker stopping...</comment>");

        return Command::SUCCESS;
    }

    /**
     * Listen for termination signals.
     */
    protected function listenForSignals(): void
    {
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);

            pcntl_signal(SIGTERM, function () { $this->shouldQuit = true; });
            pcntl_signal(SIGINT,  function () { $this->shouldQuit = true; });
        }
    }
}
