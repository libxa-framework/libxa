<?php

declare(strict_types=1);

namespace Libxa\Mail;

interface Mailer
{
    /**
     * Send a mailable to a recipient.
     */
    public function send(Mailable $mailable, string|array $to): bool;

    /**
     * Set the sender for the mailer.
     */
    public function setFrom(string $address, string $name = ''): static;
}
