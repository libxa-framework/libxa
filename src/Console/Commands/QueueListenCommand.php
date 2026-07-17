<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;
use Libxa\Queue\QueueManager;

/**
 * queue:listen
 *
 * Like queue:work, but re-resolves the queue connection on every job so
 * code changes to job classes are picked up without a manual restart.
 * Slightly slower than queue:work, intended for local development.
 */
class QueueListenCommand extends Command
{
    protected static $defaultName = 'queue:listen';

    protected bool $shouldQuit = false;

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('queue:listen')
            ->setDescription('Listen for jobs on the queue, restarting the worker loop between each job (dev-friendly alternative to queue:work)')
            ->addOption('queue', null, InputOption::VALUE_OPTIONAL, 'The name of the queue to work', 'default')
            ->addOption('sleep', null, InputOption::VALUE_OPTIONAL, 'The number of seconds to sleep when no job is available', 3)
            ->addOption('tries', null, InputOption::VALUE_OPTIONAL, 'The number of times to attempt a job before failing', 3);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queueName = $input->getOption('queue');
        $sleep     = (int) $input->getOption('sleep');
        $tries     = (int) $input->getOption('tries');

        $output->writeln("<info>Listening for jobs on [{$queueName}] queue...</info>");

        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function () { $this->shouldQuit = true; });
            pcntl_signal(SIGINT,  function () { $this->shouldQuit = true; });
        }

        while (! $this->shouldQuit) {
            // Re-resolve the queue connection fresh each iteration, so a
            // worker running via queue:listen always sees the latest code.
            /** @var QueueManager $queueManager */
            $queueManager = $this->app->make('queue');
            $connection   = $queueManager->connection();
            $job          = $connection->pop($queueName);

            if ($job) {
                $output->write("Processing job... ");

                try {
                    $job->handle();
                    $connection->deleteReserved($job->getId());
                    $output->writeln("<info>DONE</info>");
                } catch (\Throwable $e) {
                    $output->writeln("<error>FAILED</error>");
                    $output->writeln("<comment>{$e->getMessage()}</comment>");

                    if ($job->attempts() < $tries) {
                        $connection->release($job->getId(), 60);
                    } else {
                        $connection->deleteReserved($job->getId());
                    }
                }
            } else {
                sleep($sleep);
            }
        }

        $output->writeln("<comment>Listener stopping...</comment>");

        return Command::SUCCESS;
    }
}
