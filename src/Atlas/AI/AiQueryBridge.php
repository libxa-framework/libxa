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
        $enabled = \Libxa\Foundation\Application::envBool('ATLAS_AI_ENABLED', false);

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
        $enabled = \Libxa\Foundation\Application::envBool('ATLAS_AI_ENABLED', false);

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
        $logAll  = \Libxa\Foundation\Application::envBool('ATLAS_QUERY_LOG', true);

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
