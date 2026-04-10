<?php

declare(strict_types=1);

namespace Libxa\Auth\Access;

use Libxa\Foundation\Application;

class Gate
{
    protected array $abilities = [];

    public function __construct(protected Application $app)
    {
    }

    /**
     * Define a new ability.
     */
    public function define(string $ability, callable $callback): static
    {
        $this->abilities[$ability] = $callback;
        return $this;
    }

    /**
     * Determine if the given ability should be granted for the current user.
     */
    public function check(string $ability, mixed ...$arguments): bool
    {
        $user = $this->app->make('auth')->user();

        if (!$user) {
            return false;
        }

        if (!isset($this->abilities[$ability])) {
            return false;
        }

        $callback = $this->abilities[$ability];

        return $callback($user, ...$arguments);
    }

    /**
     * Determine if the given ability should be denied for the current user.
     */
    public function denies(string $ability, mixed ...$arguments): bool
    {
        return !$this->check($ability, ...$arguments);
    }

    /**
     * Determine if all of the given abilities should be granted for the current user.
     */
    public function checkAny(array $abilities, mixed ...$arguments): bool
    {
        foreach ($abilities as $ability) {
            if ($this->check($ability, ...$arguments)) {
                return true;
            }
        }

        return false;
    }
}
