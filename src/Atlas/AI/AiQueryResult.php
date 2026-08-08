<?php

declare(strict_types=1);

namespace Libxa\Atlas\AI;

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
