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

        // The hot file exists only while the dev server is running, and it
        // says which port it came up on. Trusting it rather than APP_ENV is
        // what stops a locally-served page from pointing every asset at a dev
        // server that is not there — which renders as a page with no styles
        // and no JavaScript, and no clue in the output as to why.
        if (($hot = static::hotUrl()) !== null) {
            return static::devTags((array) $entries, $hot);
        }

        $env = $app?->env('APP_ENV', 'local');

        // No hot file and no build either: a project whose Vite config predates
        // the hot file, being developed locally. The dev server is the only
        // place the assets could be coming from, so keep the old behaviour.
        if (($env === 'local' || $env === 'development') && static::loadManifest() === []) {
            return static::devTags((array) $entries);
        }

        return static::prodTags((array) $entries);
    }

    /** The dev server's URL, if one is running. */
    protected static function hotUrl(): ?string
    {
        $path = Application::getInstance()?->publicPath('hot') ?? 'src/public/hot';

        if (! is_file($path)) {
            return null;
        }

        $url = trim((string) file_get_contents($path));

        return $url !== '' ? rtrim($url, '/') : null;
    }

    protected static function devTags(array $entries, ?string $devUrl = null): string
    {
        $devUrl ??= Application::getInstance()?->env('VITE_URL', 'http://localhost:5173');
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

        // A JS entry lists the CSS it imports, and that same file is usually
        // *also* passed to @vite() explicitly: the conventional
        // @vite(['app.js', 'app.css']) emitted the stylesheet link twice.
        $emitted = [];

        $link = static function (string $href) use (&$emitted): string {
            if (isset($emitted[$href])) {
                return '';
            }

            $emitted[$href] = true;

            return "<link rel=\"stylesheet\" href=\"{$href}\">\n";
        };

        foreach ($entries as $entry) {
            $asset = $manifest[$entry] ?? null;

            if ($asset === null) continue;

            $file = '/build/' . $asset['file'];

            if (str_ends_with($file, '.css')) {
                $tags .= $link($file);
            } else {
                if (! isset($emitted[$file])) {
                    $emitted[$file] = true;
                    $tags .= "<script type=\"module\" src=\"{$file}\"></script>\n";
                }

                // Stylesheets imported by this JS entry.
                foreach ($asset['css'] ?? [] as $css) {
                    $tags .= $link('/build/' . $css);
                }
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
