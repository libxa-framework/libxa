<?php

declare(strict_types=1);

namespace Libxa\Support;

use NumberFormatter;

class Number
{
    public static function currency(float|int $number, string $currency = 'USD', string $locale = 'en_US'): string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        return $formatter->formatCurrency($number, $currency);
    }

    public static function percentage(float|int $number, int $precision = 0, string $locale = 'en_US'): string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::PERCENT);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $precision);
        return $formatter->format($number / 100);
    }

    public static function ordinal(int $number, string $locale = 'en_US'): string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::ORDINAL);
        return $formatter->format($number);
    }

    public static function spell(float|int $number, string $locale = 'en_US'): string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::SPELLOUT);
        return $formatter->format($number);
    }

    public static function bytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    public static function abbreviate(float|int $number, int $precision = 1): string
    {
        if ($number < 1000) return (string) $number;
        $units = ['', 'K', 'M', 'B', 'T'];
        for ($i = 0; $number >= 1000; $i++) {
            $number /= 1000;
        }
        return round($number, $precision) . $units[$i];
    }

    public static function clamp(float|int $number, float|int $min, float|int $max): float|int
    {
        return max($min, min($max, $number));
    }

    // Math wrappers
    public static function sqrt(float|int $number): float { return sqrt((float)$number); }
    public static function pow(float|int $base, float|int $exp): float|int { return pow($base, $exp); }
    public static function abs(float|int $number): float|int { return abs($number); }

    public static function for(float|int $number): NumberableProxy
    {
        return new NumberableProxy($number);
    }
}

/**
 * Fluent number wrapper.
 */
class NumberableProxy
{
    public function __construct(protected float|int $value) {}

    public function __call(string $method, array $args): mixed
    {
        $result = Number::$method($this->value, ...$args);
        return (is_float($result) || is_int($result)) ? new static($result) : $result;
    }

    public function value(): float|int { return $this->value; }
    public function toString(): string  { return (string) $this->value; }
    public function __toString(): string { return (string) $this->value; }
}
