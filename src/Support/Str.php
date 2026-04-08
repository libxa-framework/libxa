<?php

declare(strict_types=1);

namespace Libxa\Support;

/**
 * Str — String helper class
 */
class Str
{
    public static function camel(string $str): string
    {
        return lcfirst(static::studly($str));
    }

    public static function studly(string $str): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $str)));
    }

    public static function snake(string $str, string $delimiter = '_'): string
    {
        return strtolower(preg_replace('/[A-Z]/', $delimiter . '$0', lcfirst($str)));
    }

    public static function kebab(string $str): string
    {
        return static::snake($str, '-');
    }

    public static function slug(string $str, string $separator = '-'): string
    {
        $str  = preg_replace('/[^\pL\d]+/u', $separator, $str);
        $str  = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        $str  = preg_replace('/[^-\w]+/', '', $str);
        $str  = trim($str, $separator);
        $str  = preg_replace('/-+/', $separator, $str);
        return strtolower($str);
    }

    public static function title(string $str): string
    {
        return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
    }

    public static function upper(string $str): string { return mb_strtoupper($str, 'UTF-8'); }
    public static function lower(string $str): string { return mb_strtolower($str, 'UTF-8'); }
    public static function length(string $str): int   { return mb_strlen($str, 'UTF-8'); }

    public static function contains(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if (str_contains($haystack, $needle)) return true;
        }
        return false;
    }

    public static function startsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if (str_starts_with($haystack, $needle)) return true;
        }
        return false;
    }

    public static function endsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if (str_ends_with($haystack, $needle)) return true;
        }
        return false;
    }

    public static function limit(string $str, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($str, 'UTF-8') <= $limit) return $str;
        return rtrim(mb_substr($str, 0, $limit, 'UTF-8')) . $end;
    }

    public static function words(string $str, int $words = 100, string $end = '...'): string
    {
        preg_match('/^\s*+(?:\S+\s*+){1,' . $words . '}/u', $str, $matches);
        if (! isset($matches[0]) || mb_strlen($str) === mb_strlen($matches[0])) return $str;
        return rtrim($matches[0]) . $end;
    }

    public static function uuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public static function random(int $length = 16): string
    {
        return substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', 5)), 0, $length);
    }

    public static function token(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function plural(string $word, int $count = 2): string
    {
        if ($count === 1) return $word;

        $rules = [
            '/(quiz)$/i'               => '$1zes',
            '/^(oxen)$/i'              => '$1',
            '/^(ox)$/i'               => '$1en',
            '/(m|l)ice$/i'            => '$1ice',
            '/(m|l)ouse$/i'           => '$1ice',
            '/(pea)se$/i'             => '$1se',
            '/(shea|lea|loa|thie)f$/i' => '$1ves',
            '/(vertex|index)$/i'       => '$1ices',
            '/([^aeiou])y$/i'          => '$1ies',
            '/(x|ch|ss|sh)$/i'        => '$1es',
            '/([^aeiou])o$/i'          => '$1oes',
            '/s$/i'                    => 's',
            '/$/'                      => 's',
        ];

        foreach ($rules as $pattern => $replacement) {
            if (preg_match($pattern, $word)) {
                return preg_replace($pattern, $replacement, $word);
            }
        }

        return $word . 's';
    }

    public static function singular(string $word): string
    {
        $rules = [
            '/(quiz)zes$/i'           => '$1',
            '/(matr)ices$/i'          => '$1ix',
            '/(vert|ind)ices$/i'      => '$1ex',
            '/^(ox)en/i'              => '$1',
            '/(alias|status)es$/i'    => '$1',
            '/([octop|vir])i$/i'      => '$1us',
            '/(cris|ax|test)es$/i'    => '$1is',
            '/(shoe)s$/i'             => '$1',
            '/(o)es$/i'               => '$1',
            '/(bus)es$/i'             => '$1',
            '/([m|l])ice$/i'          => '$1ouse',
            '/(x|ch|ss|sh)es$/i'      => '$1',
            '/(m)ovies$/i'            => '$1ovie',
            '/(s)eries$/i'            => '$1eries',
            '/([^aeiouy]|qu)ies$/i'   => '$1y',
            '/([lr])ves$/i'           => '$1f',
            '/(ive)s$/i'              => '$1f',
            '/([^f])ves$/i'           => '$1fe',
            '/(shea|lea|loa|thie)ves$/i' => '$1f',
            '/(database)s$/i'         => '$1',
            '/s$/i'                   => '',
        ];

        foreach ($rules as $pattern => $replacement) {
            if (preg_match($pattern, $word)) {
                return preg_replace($pattern, $replacement, $word);
            }
        }

        return $word;
    }

    public static function padLeft(string $str, int $length, string $pad = ' '): string
    {
        return str_pad($str, $length, $pad, STR_PAD_LEFT);
    }

    public static function padRight(string $str, int $length, string $pad = ' '): string
    {
        return str_pad($str, $length, $pad, STR_PAD_RIGHT);
    }

    public static function replace(string $search, string $replace, string $subject): string
    {
        return str_replace($search, $replace, $subject);
    }

    public static function replaceArray(array $map, string $subject): string
    {
        return str_replace(array_keys($map), array_values($map), $subject);
    }

    public static function after(string $str, string $search): string
    {
        return str_contains($str, $search) ? substr($str, strpos($str, $search) + strlen($search)) : $str;
    }

    public static function before(string $str, string $search): string
    {
        return str_contains($str, $search) ? substr($str, 0, strpos($str, $search)) : $str;
    }

    public static function between(string $str, string $from, string $to): string
    {
        return static::before(static::after($str, $from), $to);
    }

    public static function wrap(string $str, string $before, string $after = ''): string
    {
        return $before . $str . ($after ?: $before);
    }

    public static function isEmail(string $str): bool
    {
        return (bool) filter_var($str, FILTER_VALIDATE_EMAIL);
    }

    public static function isUrl(string $str): bool
    {
        return (bool) filter_var($str, FILTER_VALIDATE_URL);
    }

    public static function isUuid(string $str): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $str);
    }

    public static function of(string $str): StringableProxy
    {
        return new StringableProxy($str);
    }
}

/**
 * Fluent string wrapper (clone of Laravel's Stringable).
 */
class StringableProxy
{
    public function __construct(protected string $value) {}

    public function __call(string $method, array $args): mixed
    {
        $result = Str::$method($this->value, ...$args);
        return is_string($result) ? new static($result) : $result;
    }

    public function __toString(): string { return $this->value; }
    public function toString(): string   { return $this->value; }
    public function value(): string      { return $this->value; }
}
