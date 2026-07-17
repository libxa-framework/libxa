<?php

declare(strict_types=1);

namespace Libxa\Atlas;

/**
 * Base Seeder
 *
 * All database seeders extend this class and implement run().
 */
abstract class Seeder
{
    /**
     * Run the database seeder.
     */
    abstract public function run(): void;

    /**
     * Run another seeder from within this one.
     *
     * @param string|string[] $classes
     */
    public function call(string|array $classes): void
    {
        foreach ((array) $classes as $class) {
            (new $class())->run();
        }
    }
}
