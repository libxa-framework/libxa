<?php

declare(strict_types=1);

namespace Libxa\Frontend;

use Libxa\Foundation\Application;

/**
 * Vite Manifest Reader
 *
 * Reads the Vite build manifest to generate asset tags with cache-busted hashes.
 */
class ViteManifest
{
    protected static ?array $manifest = null;
    protected static bool   $dev      = false;
    protected static string $devUrl   = 'http://localhost:5173';

    public static function tags(array|string $entries): string
    {
        $app = Application::getInstance();
        $env = $app?->env('APP_ENV', 'local');

        // In local dev — use Vite dev server
        if ($env === 'local' || $env === 'development') {
            return static::devTags((array) $entries);
        }

        return static::prodTags((array) $entries);
    }

    protected static function devTags(array $entries): string
    {
        $devUrl = Application::getInstance()?->env('VITE_URL', 'http://localhost:5173');
        $tags   = "<script type=\"module\" src=\"$devUrl/@vite/client\"></script>\n";

        foreach ($entries as $entry) {
            if (str_ends_with($entry, '.css')) {
                $tags .= "<link rel=\"stylesheet\" href=\"$devUrl/$entry\">\n";
            } else {
                $tags .= "<script type=\"module\" src=\"$devUrl/$entry\"></script>\n";
            }
        }

        return $tags;
    }

    protected static function prodTags(array $entries): string
    {
        $manifest = static::loadManifest();
        $tags     = '';

        foreach ($entries as $entry) {
            $asset = $manifest[$entry] ?? null;

            if ($asset === null) continue;

            $file = '/build/' . $asset['file'];

            if (str_ends_with($file, '.css')) {
                $tags .= "<link rel=\"stylesheet\" href=\"$file\">\n";
            } else {
                $tags .= "<script type=\"module\" src=\"$file\"></script>\n";
            }

            // Also load CSS for JS entry points
            foreach ($asset['css'] ?? [] as $css) {
                $tags .= "<link rel=\"stylesheet\" href=\"/build/$css\">\n";
            }
        }

        return $tags;
    }

    protected static function loadManifest(): array
    {
        if (static::$manifest !== null) {
            return static::$manifest;
        }

        $app  = Application::getInstance();
        $path = $app?->publicPath('build/manifest.json') ?? 'src/public/build/manifest.json';

        if (! file_exists($path)) {
            return static::$manifest = [];
        }

        return static::$manifest = json_decode(file_get_contents($path), true) ?? [];
    }
}
