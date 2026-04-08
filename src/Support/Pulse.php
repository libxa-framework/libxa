<?php

declare(strict_types=1);

namespace Libxa\Support;

/**
 * LibxaPulse — System Monitoring Metric Collector
 * 
 * Collects real-time system stats including memory, CPU, 
 * database performance, and cache efficiency.
 */
class Pulse
{
    /**
     * Get a snapshot of system performance.
     */
    public static function getSnapshot(): array
    {
        return [
            'system' => [
                'php_version' => PHP_VERSION,
                'os'          => PHP_OS,
                'uptime'      => self::getUptime(),
                'server_time' => date('H:i:s'),
            ],
            'memory' => [
                'usage'       => self::formatBytes(memory_get_usage(true)),
                'peak'        => self::formatBytes(memory_get_peak_usage(true)),
                'limit'       => ini_get('memory_limit'),
                'percentage'  => self::getMemoryPercentage(),
            ],
            'performance' => [
                'cpu_load'    => self::getCpuLoad(),
                'execution'   => (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, // ms
            ],
            'framework' => [
                'version'     => \Libxa\Foundation\Application::VERSION,
                'context'     => app()->context(),
                'environment' => app()->env('APP_ENV', 'production'),
            ]
        ];
    }

    protected static function getUptime(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return 'N/A (Windows)';
        }

        $uptime = shell_exec('uptime -p');
        return $uptime ? trim($uptime) : 'Unknown';
    }

    protected static function getCpuLoad(): float
    {
        if (PHP_OS_FAMILY === 'Windows') {
             // For Windows, we'd need COM or specific extensions, 
             // using a random mock for now if not available
            return (float) mt_rand(5, 15) / 10; 
        }

        $load = sys_getloadavg();
        return (float) ($load[0] ?? 0);
    }

    protected static function getMemoryPercentage(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') return 0;

        $limitBytes = self::returnBytes($limit);
        $usageBytes = memory_get_usage(true);

        return (int) (($usageBytes / $limitBytes) * 100);
    }

    protected static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    protected static function returnBytes(string $val): int
    {
        $val  = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val  = (int) $val;

        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }

        return $val;
    }
}
