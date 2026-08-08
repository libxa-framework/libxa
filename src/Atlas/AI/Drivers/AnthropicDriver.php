<?php

declare(strict_types=1);

namespace Libxa\Atlas\AI\Drivers;

class AnthropicDriver implements \Libxa\Atlas\AI\AiDriverContract
{
    public function generateSql(string $question, string $schemaContext): string
    {
        throw new \RuntimeException('Anthropic driver not yet implemented.');
    }

    public function generateScope(string $description, string $schemaContext): string
    {
        throw new \RuntimeException('Anthropic driver not yet implemented.');
    }
}
