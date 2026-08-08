<?php

declare(strict_types=1);

namespace Libxa\Atlas\AI\Drivers;

class TestDriver implements \Libxa\Atlas\AI\AiDriverContract
{
    public function generateSql(string $question, string $schemaContext): string
    {
        // Simple heuristic for testing without making API calls
        if (stripos($question, 'count') !== false) {
            return "SELECT COUNT(*) FROM users;";
        }
        
        return "SELECT * FROM users LIMIT 10;";
    }

    public function generateScope(string $description, string $schemaContext): string
    {
        return "return \$query->where('active', 1);";
    }
}
