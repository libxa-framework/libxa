<?php

declare(strict_types=1);

namespace Libxa\Mail;

use Libxa\Foundation\Application;

class MailManager
{
    /**
     * Created mailers.
     */
    protected array $mailers = [];

    /**
     * Create a new mail manager instance.
     */
    public function __construct(protected Application $app)
    {
    }

    /**
     * Send a mailable to a recipient using the default mailer.
     */
    public function send(Mailable $mailable, string|array $to): bool
    {
        return $this->mailer()->send($mailable, $to);
    }

    /**
     * Get a mailer instance by name.
     */
    public function mailer(?string $name = null): Mailer
    {
        $name = $name ?: $this->getDefaultDriver();

        if (isset($this->mailers[$name])) {
            return $this->mailers[$name];
        }

        return $this->mailers[$name] = $this->resolve($name);
    }

    /**
     * Resolve the given mailer.
     */
    protected function resolve(string $name): Mailer
    {
        $config = $this->app->config("mail.mailers.{$name}");

        $driverMethod = 'create' . ucfirst($config['driver'] ?? $name) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            $mailer = $this->{$driverMethod}($config);
            
            // Set global "from" address if configured
            $from = $this->app->config('mail.from');
            if ($from && isset($from['address'])) {
                $mailer->setFrom($from['address'], $from['name'] ?? '');
            }

            return $mailer;
        }

        throw new \InvalidArgumentException("Mailer driver [{$name}] is not supported.");
    }

    /**
     * Create a new Log mailer driver.
     */
    protected function createLogDriver(array $config): Mailer
    {
        return new LogMailer($this->app);
    }

    /**
     * Create a new Smtp mailer driver.
     */
    protected function createSmtpDriver(array $config): Mailer
    {
        return new SmtpMailer($this->app);
    }

    /**
     * Get the default mail driver name.
     */
    protected function getDefaultDriver(): string
    {
        return $this->app->config('mail.default', 'log');
    }
}
