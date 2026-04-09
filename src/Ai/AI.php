<?php

declare(strict_types=1);

namespace Libxa\Ai;

use Libxa\Foundation\Application;

/**
 * AI Facade (Static Entry Point)
 */
class AI
{
    /**
     * Get the AI manager instance.
     */
    protected static function manager(): AiManager
    {
        return Application::getInstance()->make('ai');
    }

    public static function text(string $prompt, array $options = []): string
    {
        return static::manager()->text($prompt, $options);
    }

    public static function classify(string $text, array $labels): string
    {
        return static::manager()->classify($text, $labels);
    }

    public static function embed(string|array $text): array
    {
        return static::manager()->embed($text);
    }

    public static function extract(string $text, array $schema): array
    {
        return static::manager()->extract($text, $schema);
    }

    public static function summarize(string $text, int $maxLength = 200): string
    {
        return static::manager()->summarize($text, $maxLength);
    }

    public static function translate(string $text, string $targetLanguage): string
    {
        return static::manager()->translate($text, $targetLanguage);
    }
}
