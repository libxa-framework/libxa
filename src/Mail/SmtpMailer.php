<?php

declare(strict_types=1);

namespace Libxa\Mail;

use Libxa\Foundation\Application;

class SmtpMailer implements Mailer
{
    /**
     * The sender information.
     */
    protected array $from = [];

    /**
     * Create a new SmtpMailer instance.
     */
    public function __construct(protected Application $app)
    {
    }

    /**
     * Send a mailable to a recipient using PHP's native `mail()` function.
     * Note: In a production-ready framework, this should ideally integrate with
     * a library like Symfony Mailer for better reliability and performance.
     */
    public function send(Mailable $mailable, string|array $to): bool
    {
        $mailable->build();
        
        $from = $mailable->getFrom() ?: $this->from;
        $toAddress = is_array($to) ? implode(', ', $to) : $to;
        $content = $this->app->make('blade')->render($mailable->getView(), $mailable->getViewData());

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $from['name'] ?? 'LibxaFrame', $from['address'] ?? 'no-reply@Libxaframe.com'),
            'Reply-To: ' . ($from['address'] ?? 'no-reply@Libxaframe.com'),
            'X-Mailer: PHP/' . PHP_VERSION,
        ];

        return mail($toAddress, $mailable->getSubject(), $content, implode("\r\n", $headers));
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
