<?php

declare(strict_types=1);

namespace Libxa\Validation;

/**
 * MessageBag
 * 
 * Simple container for validation messages.
 */
class MessageBag
{
    public function __construct(
        protected array $messages = []
    ) {}

    /**
     * Determine if messages exist for the given key.
     */
    public function has(string $key): bool
    {
        return isset($this->messages[$key]) && ! empty($this->messages[$key]);
    }

    /**
     * Get the first message for a given key.
     */
    public function first(string $key, ?string $default = null): ?string
    {
        return $this->get($key)[0] ?? $default;
    }

    /**
     * Get all messages for a given key.
     */
    public function get(string $key): array
    {
        if (isset($this->messages[$key])) {
            return (array) $this->messages[$key];
        }

        return [];
    }

    /**
     * Get all messages in the bag.
     */
    public function all(): array
    {
        return $this->messages;
    }

    /**
     * Determine if the bag is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->messages);
    }

    /**
     * Determine if any messages exist in the bag.
     */
    public function any(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Get the messages as an array (alias for all()).
     */
    public function toArray(): array
    {
        return $this->messages;
    }

    /**
     * Merge another bag's messages into this one.
     */
    public function merge(array|MessageBag $messages): static
    {
        $data = $messages instanceof MessageBag ? $messages->all() : $messages;
        foreach ($data as $key => $value) {
            $this->messages[$key] = array_merge(
                $this->messages[$key] ?? [],
                (array) $value
            );
        }
        return $this;
    }

    /**
     * Add a message for a key.
     */
    public function add(string $key, string $message): static
    {
        $this->messages[$key][] = $message;
        return $this;
    }

    /**
     * Count total messages.
     */
    public function count(): int
    {
        return array_sum(array_map('count', array_map(fn($v) => (array)$v, $this->messages)));
    }
}
