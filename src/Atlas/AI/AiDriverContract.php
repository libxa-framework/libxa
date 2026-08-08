<?php

declare(strict_types=1);

namespace Libxa\Atlas\AI;

// ─────────────────────────────────────────────────────────────────────
//  Driver Contract
// ─────────────────────────────────────────────────────────────────────

interface AiDriverContract
{
    /** Generate a SQL SELECT query from an English question and schema context */
    public function generateSql(string $question, string $schemaContext): string;

    /** Generate a PHP scope method body from an English description */
    public function generateScope(string $description, string $schemaContext): string;
}
