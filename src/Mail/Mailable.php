<?php

declare(strict_types=1);

namespace Libxa\Mail;

abstract class Mailable
{
    /**
     * The sender of the message.
     */
    protected array $from = [];

    /**
     * The subject of the message.
     */
    protected string $subject = '';

    /**
     * The Blade view for the message.
     */
    protected string $view = '';

    /**
     * The data for the message view.
     */
    protected array $viewData = [];

    /**
     * Set the sender of the message.
     */
    public function from(string $address, string $name = ''): static
    {
        $this->from = compact('address', 'name');

        return $this;
    }

    /**
     * Set the subject of the message.
     */
    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Set the view for the message.
     */
    public function view(string $view, array $data = []): static
    {
        $this->view = $view;
        $this->viewData = array_merge($this->viewData, $data);

        return $this;
    }

    /**
     * Build the message.
     */
    abstract public function build(): void;

    /**
     * Get the sender information.
     */
    public function getFrom(): array
    {
        return $this->from;
    }

    /**
     * Get the subject.
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * Get the view name.
     */
    public function getView(): string
    {
        return $this->view;
    }

    /**
     * Get the view data.
     */
    public function getViewData(): array
    {
        return $this->viewData;
    }
}
