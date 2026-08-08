<?php

declare(strict_types=1);

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
