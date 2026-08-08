<?php

declare(strict_types=1);

/**
 * Syntax-check every PHP file in the given directories.
 *
 * Uses token_get_all(..., TOKEN_PARSE), which runs the real PHP parser
 * in-process and throws ParseError on invalid syntax. That makes this a
 * genuine syntax check while staying roughly a hundred times faster than
 * spawning `php -l` per file — process creation dominates on Windows, where
 * a 200-file tree took minutes.
 *
 * Usage: php tools/lint.php [dir ...]      (defaults to src and tests)
 */

$directories = array_slice($argv, 1) ?: ['src', 'tests'];
$root        = dirname(__DIR__);

$failures = [];
$checked  = 0;
$started  = microtime(true);

foreach ($directories as $directory) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory);

    // A hard error, not a warning: silently skipping a mistyped or moved
    // directory would make `composer lint` exit 0 while checking nothing.
    if (! is_dir($path)) {
        fwrite(STDERR, "error: [{$directory}] is not a directory (looked in {$path})\n");
        exit(1);
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $checked++;
        $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
        $source   = file_get_contents($file->getPathname());

        if ($source === false) {
            $failures[$relative] = 'could not be read';
            continue;
        }

        // A UTF-8 BOM is emitted as output before any header() call, which
        // breaks every response. It is not a parse error, so php -l misses it.
        if (str_starts_with($source, "\xEF\xBB\xBF")) {
            $failures[$relative] = 'starts with a UTF-8 BOM (emits output before headers)';
            continue;
        }

        try {
            token_get_all($source, TOKEN_PARSE);
        } catch (ParseError $e) {
            $failures[$relative] = $e->getMessage() . ' on line ' . $e->getLine();
        }
    }
}

$elapsed = round((microtime(true) - $started) * 1000);

if ($failures !== []) {
    fwrite(STDERR, "\n" . count($failures) . ' of ' . $checked . " file(s) failed:\n\n");

    foreach ($failures as $file => $message) {
        fwrite(STDERR, "  {$file}\n      {$message}\n\n");
    }

    exit(1);
}

echo "No syntax errors in {$checked} files ({$elapsed}ms).\n";
exit(0);
