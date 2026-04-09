<?php

declare(strict_types=1);

namespace Libxa\Ai;

use Libxa\Http\Client;
use Libxa\Foundation\Application;

/**
 * LibxaFrame AI Manager
 * 
 * Handles interaction with LLM providers (default: OpenAI).
 */
class AiManager
{
    protected string $provider;
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;
    protected Client $client;

    public function __construct(protected Application $app)
    {
        $this->provider = $app->env('AI_PROVIDER', 'openai');
        $this->apiKey   = $app->env('OPENAI_API_KEY', '');
        $this->model    = $app->env('AI_MODEL', 'gpt-4o-mini');
        $this->baseUrl  = rtrim($app->env('AI_BASE_URL', 'https://api.openai.com/v1'), '/');
        
        $this->client = new Client([
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type'  => 'application/json',
            ]
        ]);
    }

    /**
     * Generate text from a prompt.
     */
    public function text(string $prompt, array $options = []): string
    {
        $url = $this->baseUrl . '/chat/completions';
        $response = $this->client->post($url, [
            'model' => $options['model'] ?? $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => $options['temperature'] ?? 0.7,
        ]);

        return $response['body']['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Classify text into given labels.
     */
    public function classify(string $text, array $labels): string
    {
        $labelList = implode(', ', $labels);
        $prompt = "Classify the following text into one of these labels: {$labelList}.\n\nText: \"{$text}\"\n\nLabel:";
        
        return trim($this->text($prompt, ['temperature' => 0]));
    }

    /**
     * Generate embeddings for text.
     */
    public function embed(string|array $text): array
    {
        $url = $this->baseUrl . '/embeddings';
        $response = $this->client->post($url, [
            'model' => 'text-embedding-3-small',
            'input' => $text,
        ]);

        if (is_array($text)) {
            return array_map(fn($item) => $item['embedding'], $response['body']['data'] ?? []);
        }

        return $response['body']['data'][0]['embedding'] ?? [];
    }

    /**
     * Extract structured data from text using a schema.
     */
    public function extract(string $text, array $schema): array
    {
        $schemaJson = json_encode($schema);
        $prompt = "Extract the following information from the text provided below. Return ONLY a JSON object matching this schema: {$schemaJson}\n\nText: \"{$text}\"";

        $response = $this->text($prompt, ['temperature' => 0]);
        $data = json_decode($this->cleanJsonResponse($response), true);

        return $data ?? [];
    }

    /**
     * Summarize the given text.
     */
    public function summarize(string $text, int $maxLength = 200): string
    {
        $prompt = "Summarize the following text in about {$maxLength} characters:\n\n{$text}";
        return $this->text($prompt);
    }

    /**
     * Translate text to a target language.
     */
    public function translate(string $text, string $targetLanguage): string
    {
        $prompt = "Translate the following text to {$targetLanguage}. Return ONLY the translated text:\n\n{$text}";
        return $this->text($prompt, ['temperature' => 0]);
    }

    /**
     * Helper to clean up JSON responses from LLMs (removing markdown code blocks).
     */
    protected function cleanJsonResponse(string $response): string
    {
        $response = trim($response);
        if (str_starts_with($response, '```json')) {
            $response = substr($response, 7);
        }
        if (str_ends_with($response, '```')) {
            $response = substr($response, 0, -3);
        }
        return trim($response);
    }
}
