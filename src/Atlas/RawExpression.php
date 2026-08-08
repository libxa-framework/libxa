<?php

declare(strict_types=1);

namespace Libxa\Atlas;

/**
 * Raw SQL expression wrapper (prevents quoting/escaping).
 *
 * This class used to be declared at the bottom of QueryBuilder.php. PSR-4
 * maps one class per file, so `new RawExpression(...)` from anywhere that had
 * not already loaded QueryBuilder.php failed with "Class not found".
 */
class RawExpression implements \Stringable
{
    public function __construct(protected string $value) {}

    public function __toString(): string
    {
        return $this->value;
    }
}
