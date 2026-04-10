<?php

declare(strict_types=1);

namespace Libxa\Atlas\AI;

/**
 * Atlas AI Query Bridge — STUB
 *
 * Converts natural-language English questions into safe, read-only SQL
 * using the configured LLM provider (OpenAI, Anthropic, Gemini, etc.).
 *
 * ┌──────────────────────────────────────────────────────────────────┐
 * │  This is an INTERFACE-READY STUB.                                │
 * │  To enable real AI queries:                                      │
 * │    1. Set ATLAS_AI_ENABLED=true in .env                          │
 * │    2. Set ATLAS_AI_PROVIDER=openai (or anthropic/gemini)         │
 * │    3. Set ATLAS_AI_KEY=your-api-key                              │
 * │    4. Implement a driver in Libxa\Atlas\AI\Drivers\               │
 * └──────────────────────────────────────────────────────────────────┘
 *
 * Usage:
 *   $result = DB::ask("top 10 users by revenue in the last 30 days");
 *   $result->sql    // Generated SQL string
 *   $result->safe   // true (read-only validation passed)
 *   $result->data   // the actual query results
 *
 *   // On a specific model
 *   User::ask("users who haven't logged in for 90 days");
 */
class AiQueryBridge
{
    /** Disallow any SQL that modifies data */
    protected const BLOCKED_KEYWORDS = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'REPLACE'];

    /**
     * Ask a natural-language question and get results.
     *
     * @param  string       $question   Natural language query
     * @param  string|null  $modelClass Optional model to scope the schema context
     */
    public static function ask(string $question, ?string $modelClass = null): AiQueryResult
    {
        $app     = \Libxa\Foundation\Application::getInstance();
        $enabled = $app?->env('ATLAS_AI_ENABLED', 'false') === 'true';

        if (! $enabled) {
            return AiQueryResult::disabled($question);
        }

        $provider = $app?->env('ATLAS_AI_PROVIDER', 'openai');
        $driver   = static::resolveDriver($provider);

        if ($driver === null) {
            return AiQueryResult::noDriver($question, $provider);
        }

        // Build schema context
        $schema = static::buildSchemaContext($modelClass);

        // Generate SQL from the LLM
        $sql = $driver->generateSql($question, $schema);

        // Safety validation: never run destructive SQL
        if (! static::isSafe($sql)) {
            return AiQueryResult::unsafe($question, $sql);
        }

        // Log the generated query
        static::logQuery($question, $sql);

        // Execute
        try {
            $pdo  = \Libxa\Atlas\Connection\ConnectionPool::getInstance()->get();
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return new AiQueryResult(
                question: $question,
                sql:      $sql,
                safe:     true,
                data:     $data,
                error:    null,
            );
        } catch (\Throwable $e) {
            return AiQueryResult::executionError($question, $sql, $e->getMessage());
        }
    }

    /**
     * Generate PHP scope code from a natural-language description.
     * Useful as a dev-time scaffolding tool.
     */
    public static function generate(string $description, ?string $modelClass = null): string
    {
        $app     = \Libxa\Foundation\Application::getInstance();
        $enabled = $app?->env('ATLAS_AI_ENABLED', 'false') === 'true';

        if (! $enabled) {
            return "// AI Query Bridge disabled. Set ATLAS_AI_ENABLED=true in .env\n";
        }

        $provider = $app?->env('ATLAS_AI_PROVIDER', 'openai');
        $driver   = static::resolveDriver($provider);

        if ($driver === null) {
            return "// Driver '$provider' not implemented yet.\n";
        }

        $schema = static::buildSchemaContext($modelClass);
        return $driver->generateScope($description, $schema);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Safety Gate
    // ─────────────────────────────────────────────────────────────────

    protected static function isSafe(string $sql): bool
    {
        $upper = strtoupper(trim($sql));

        foreach (static::BLOCKED_KEYWORDS as $keyword) {
            // Match keyword at word boundary
            if (preg_match("/\b$keyword\b/", $upper)) {
                return false;
            }
        }

        // Must start with SELECT
        return str_starts_with($upper, 'SELECT');
    }

    // ─────────────────────────────────────────────────────────────────
    //  Schema Context Builder
    // ─────────────────────────────────────────────────────────────────

    protected static function buildSchemaContext(?string $modelClass): string
    {
        if ($modelClass === null) {
            return '-- No schema context provided';
        }

        try {
            $model  = new $modelClass();
            $table  = $model->getTable();
            $pdo    = \Libxa\Atlas\Connection\ConnectionPool::getInstance()->get();

            // Get columns
            $stmt = $pdo->query("PRAGMA table_info(`$table`)"); // SQLite
            $cols = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

            if (empty($cols)) {
                // Try MySQL
                $stmt = $pdo->query("DESCRIBE `$table`");
                $cols = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            }

            $colDefs = array_map(fn($c) => "  {$c['name']} {$c['type']}", $cols);

            return "CREATE TABLE $table (\n" . implode(",\n", $colDefs) . "\n);";
        } catch (\Throwable) {
            return "-- Could not resolve schema for $modelClass";
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Driver resolution
    // ─────────────────────────────────────────────────────────────────

    protected static function resolveDriver(string $provider): ?AiDriverContract
    {
        $drivers = [
            'openai'    => Drivers\OpenAiDriver::class,
            'anthropic' => Drivers\AnthropicDriver::class,
            'gemini'    => Drivers\GeminiDriver::class,
            'test'      => Drivers\TestDriver::class,
        ];

        $class = $drivers[$provider] ?? null;

        if ($class === null || ! class_exists($class)) {
            return null;
        }

        return new $class();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Logging
    // ─────────────────────────────────────────────────────────────────

    protected static function logQuery(string $question, string $sql): void
    {
        $app     = \Libxa\Foundation\Application::getInstance();
        $logAll  = $app?->env('ATLAS_QUERY_LOG', 'true') === 'true';

        if (! $logAll) return;

        $log = sprintf(
            "[%s] AI Query\nQuestion: %s\nSQL: %s\n---\n",
            date('Y-m-d H:i:s'),
            $question,
            $sql
        );

        $logDir = $app?->storagePath('logs') ?? 'storage/logs';
        if (! is_dir($logDir)) mkdir($logDir, 0755, true);

        file_put_contents("$logDir/atlas-ai.log", $log, FILE_APPEND | LOCK_EX);
    }
}

// ─────────────────────────────────────────────────────────────────────
//  Result Object
// ─────────────────────────────────────────────────────────────────────

final class AiQueryResult
{
    public function __construct(
        public readonly string  $question,
        public readonly string  $sql,
        public readonly bool    $safe,
        public readonly array   $data,
        public readonly ?string $error,
        public readonly string  $status = 'ok',
    ) {}

    public static function disabled(string $question): static
    {
        return new static($question, '', false, [], 'AI Query Bridge is disabled. Set ATLAS_AI_ENABLED=true.', 'disabled');
    }

    public static function noDriver(string $question, string $provider): static
    {
        return new static($question, '', false, [], "No driver implemented for provider: $provider. Create Libxa\\Atlas\\AI\\Drivers\\{$provider}Driver.", 'no_driver');
    }

    public static function unsafe(string $question, string $sql): static
    {
        return new static($question, $sql, false, [], 'Generated SQL was blocked (contains destructive operation).', 'unsafe');
    }

    public static function executionError(string $question, string $sql, string $error): static
    {
        return new static($question, $sql, true, [], $error, 'execution_error');
    }

    public function succeeded(): bool { return $this->status === 'ok'; }
    public function failed(): bool    { return $this->status !== 'ok'; }

    public function toArray(): array
    {
        return [
            'question' => $this->question,
            'sql'      => $this->sql,
            'safe'     => $this->safe,
            'data'     => $this->data,
            'error'    => $this->error,
            'status'   => $this->status,
        ];
    }
}

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

// ─────────────────────────────────────────────────────────────────────
//  Driver Stubs — implement these to enable AI queries
// ─────────────────────────────────────────────────────────────────────

namespace Libxa\Atlas\AI\Drivers;

use Libxa\Http\Client;

class OpenAiDriver implements \Libxa\Atlas\AI\AiDriverContract
{
    public function generateSql(string $question, string $schemaContext): string
    {
        $app = \Libxa\Foundation\Application::getInstance();
        $key = $app->env('OPENAI_API_KEY');
        
        if (empty($key)) {
            throw new \RuntimeException('Missing OPENAI_API_KEY in .env');
        }

        $dbDriver = strtoupper($app->env('DB_DRIVER', 'sqlite'));

        $client = new Client([
            'headers' => [
                'Authorization' => "Bearer {$key}",
                'Content-Type'  => 'application/json',
            ]
        ]);

        $prompt = "Given this database schema:\n{$schemaContext}\n\nWrite a safe, read-only $dbDriver SQL SELECT query for the following request:\n\"{$question}\"\n\nReturn ONLY the SQL string, no markdown, no explanation. Ensure functions and syntax are strict $dbDriver compatible.";


        $baseUrl = rtrim($app->env('AI_BASE_URL', 'https://api.openai.com/v1'), '/');
        $response = $client->post($baseUrl . '/chat/completions', [
            'model' => $app->env('ATLAS_AI_MODEL', 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'system', 'content' => 'You are a SQL expert. You only output raw SQL SELECT statements.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0,
        ]);

        if (isset($response['body']['error'])) {
            throw new \RuntimeException('AI API Error: ' . json_encode($response['body']['error']));
        }

        $sql = $response['body']['choices'][0]['message']['content'] ?? '';
        if ($sql === '') {
            throw new \RuntimeException('AI API returned empty content: ' . json_encode($response['body']));
        }
        
        return $this->cleanSql($sql);

    }

    public function generateScope(string $description, string $schemaContext): string
    {
        $app = \Libxa\Foundation\Application::getInstance();
        $key = $app->env('OPENAI_API_KEY');

        $client = new Client([
            'headers' => [
                'Authorization' => "Bearer {$key}",
                'Content-Type'  => 'application/json',
            ]
        ]);

        $prompt = "Given this database schema:\n{$schemaContext}\n\nGenerate a PHP method body for an Atlas ORM scope that fulfills this description:\n\"{$description}\"\n\nUse standard Libxa\\Atlas\\QueryBuilder methods like where(), orderBy(), limit().\n\nReturn ONLY the PHP code, no tags, no markdown.";

        $baseUrl = rtrim($app->env('AI_BASE_URL', 'https://api.openai.com/v1'), '/');
        $response = $client->post($baseUrl . '/chat/completions', [
            'model' => $app->env('ATLAS_AI_MODEL', 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'system', 'content' => 'You are a PHP framework expert.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0,
        ]);

        return trim($response['body']['choices'][0]['message']['content'] ?? '', " \t\n\r\0\x0B` ");
    }

    protected function cleanSql(string $sql): string
    {
        // Try to extract content inside ```sql ... ``` block
        if (preg_match('/```sql\s*(.*?)\s*```/is', $sql, $matches)) {
            $sql = $matches[1];
        } elseif (preg_match('/```(.*?)```/is', $sql, $matches)) {
            $sql = $matches[1];
        } else {
            // Find the first occurrence of SELECT to ignore conversational prefixes
            $pos = stripos($sql, 'SELECT');
            if ($pos !== false) {
                $sql = substr($sql, $pos);
            }
        }
        
        return trim($sql, " \t\n\r\0\x0B;");
    }

}

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

class GeminiDriver implements \Libxa\Atlas\AI\AiDriverContract
{
    public function generateSql(string $question, string $schemaContext): string
    {
        throw new \RuntimeException('Gemini driver not yet implemented.');
    }

    public function generateScope(string $description, string $schemaContext): string
    {
        throw new \RuntimeException('Gemini driver not yet implemented.');
    }
}

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
