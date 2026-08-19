<?php

declare(strict_types=1);

namespace Libxa\Atlas\Connection;

/**
 * The requested database driver is not compiled into this PHP.
 *
 * PDO's own message for this is "could not find driver" — six words that name
 * neither the driver, nor the extension to install, nor the php.ini that would
 * have to change. It is thrown identically whether you asked for MySQL,
 * Postgres or SQLite, so it cannot even tell you which one you are missing.
 *
 * This exists to replace it with something a person can act on.
 */
class DriverUnavailableException extends \RuntimeException
{
    public function __construct(
        public readonly string $driver,
        public readonly array $available,
    ) {
        parent::__construct($this->explain());
    }

    private function explain(): string
    {
        $extension = ConnectionPool::extensionFor($this->driver);

        $lines = [
            sprintf('Database driver "%s" is not available in this PHP build.', $this->driver),
            '',
            sprintf('  PHP binary : %s', PHP_BINARY),
            sprintf('  PHP version: %s', PHP_VERSION),
        ];

        if ($ini = php_ini_loaded_file()) {
            $lines[] = sprintf('  php.ini    : %s', $ini);
        } else {
            $lines[] = '  php.ini    : none loaded';
        }

        $lines[] = sprintf(
            '  PDO has    : %s',
            $this->available === [] ? '(no drivers at all)' : implode(', ', $this->available),
        );

        $lines[] = '';

        if ($this->available === []) {
            // Every driver missing points at one cause, and it is not the
            // individual extensions: PHP cannot find its extension directory.
            $lines[] = 'PDO has no drivers at all, which usually means extension_dir in php.ini';
            $lines[] = 'is wrong rather than that each driver is separately missing.';
        } else {
            $lines[] = sprintf('Enable it by adding this line to php.ini and restarting PHP:');
            $lines[] = '';
            $lines[] = sprintf('    extension=%s', $extension);
        }

        $lines[] = '';
        $lines[] = 'If you have several PHP versions installed, check that the one shown';
        $lines[] = 'above is the one you meant to run.';

        return implode(PHP_EOL, $lines);
    }
}
