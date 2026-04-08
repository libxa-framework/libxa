<?php

declare(strict_types=1);

namespace Libxa\Mail;

use Libxa\Foundation\Application;

class LogMailer implements Mailer
{
    /**
     * The sender information.
     */
    protected array $from = [];

    /**
     * Create a new LogMailer instance.
     */
    public function __construct(protected Application $app)
    {
    }

    /**
     * Store the mailable content in the application log.
     */
    public function send(Mailable $mailable, string|array $to): bool
    {
        $mailable->build();

        $from = $mailable->getFrom() ?: $this->from;
        
        $receivers = is_array($to) ? implode(', ', $to) : $to;
        $content = $this->app->make('blade')->render($mailable->getView(), $mailable->getViewData());

        $logMessage = sprintf(
            "----- [NexMail-Log] -----\nFrom: %s <%s>\nTo: %s\nSubject: %s\nContent:\n%s\n-------------------------",
            $from['name'] ?? 'LibxaFrame',
            $from['address'] ?? 'no-reply@Libxaframe.com',
            $receivers,
            $mailable->getSubject(),
            $content
        );

        return (bool) file_put_contents(
            $this->app->storagePath('logs/mail.log'),
            $logMessage . PHP_EOL . PHP_EOL,
            FILE_APPEND
        );
    }

    /**
     * Set the sender for the mailer.
     */
    public function setFrom(string $address, string $name = ''): static
    {
        $this->from = compact('address', 'name');

        return $this;
    }
}
