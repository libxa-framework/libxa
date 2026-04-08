<?php

declare(strict_types=1);

namespace Libxa\Reactive;

/**
 * DOM Diff Engine
 *
 * Computes a minimal diff between two HTML strings.
 * Produces an array of patch operations sent to the client via WebSocket.
 *
 * Operations:
 *   - replace: Replace the full HTML of an element by ID
 *   - text:    Update just the text content of an element
 *   - attr:    Update a specific attribute
 *   - remove:  Remove an element
 *   - append:  Append new HTML to a parent element
 *
 * LibxaFrame uses ID-based diffing (not virtual DOM tree diffing).
 * Each reactive component wraps itself in a data-id="..." container.
 */
class DiffEngine
{
    /**
     * Compute a diff between old and new HTML.
     *
     * @return array<array{op: string, target: string, value?: string, attr?: string}>
     */
    public static function diff(string $oldHtml, string $newHtml): array
    {
        if ($oldHtml === $newHtml) {
            return [];
        }

        $ops = [];

        // Parse elements with IDs from both HTML strings
        $oldElements = static::extractElementsById($oldHtml);
        $newElements = static::extractElementsById($newHtml);

        // Find changed or new elements
        foreach ($newElements as $id => $newContent) {
            $oldContent = $oldElements[$id] ?? null;

            if ($oldContent === null) {
                // New element added — try to append to parent
                $ops[] = [
                    'op'     => 'append',
                    'target' => 'body',
                    'value'  => $newContent,
                ];
            } elseif ($oldContent !== $newContent) {
                // Check if only text changed
                $oldText = static::stripTags($oldContent);
                $newText = static::stripTags($newContent);

                if ($oldText !== $newText && static::structureSame($oldContent, $newContent)) {
                    $ops[] = [
                        'op'     => 'text',
                        'target' => $id,
                        'value'  => $newText,
                    ];
                } else {
                    // Full replacement
                    $ops[] = [
                        'op'     => 'replace',
                        'target' => $id,
                        'value'  => $newContent,
                    ];
                }
            }
        }

        // Find removed elements
        foreach ($oldElements as $id => $content) {
            if (! isset($newElements[$id])) {
                $ops[] = [
                    'op'     => 'remove',
                    'target' => $id,
                ];
            }
        }

        // Fallback: if no element IDs found, send full replace
        if (empty($ops) && $oldHtml !== $newHtml) {
            $ops[] = [
                'op'    => 'replace_all',
                'value' => $newHtml,
            ];
        }

        return $ops;
    }

    /**
     * Extract elements that have an id attribute.
     *
     * @return array<string, string>  [id => outerHTML]
     */
    protected static function extractElementsById(string $html): array
    {
        $elements = [];

        // Match opening tags with id attributes
        preg_match_all('/(<[a-z][a-z0-9]*[^>]+id=[\'"]([^\'"]+)[\'"][^>]*>)/i', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $id  = $match[2];
            $tag = strtolower(preg_match('/<([a-z][a-z0-9]*)/i', $match[1], $m) ? $m[1] : 'div');

            // Find the full element (including closing tag)
            $start = strpos($html, $match[0]);
            if ($start === false) continue;

            $content = static::extractElement($html, $start, $tag);
            if ($content !== null) {
                $elements[$id] = $content;
            }
        }

        return $elements;
    }

    /**
     * Extract the full outer HTML of an element starting at $start.
     */
    protected static function extractElement(string $html, int $start, string $tag): ?string
    {
        $depth  = 0;
        $pos    = $start;
        $len    = strlen($html);
        $result = '';

        while ($pos < $len) {
            // Find next opening or closing tag
            $nextOpen  = strpos($html, "<$tag", $pos);
            $nextClose = strpos($html, "</$tag>", $pos);

            if ($nextClose === false) break;

            if ($nextOpen !== false && $nextOpen < $nextClose) {
                $depth++;
                $pos = $nextOpen + strlen($tag) + 1;
            } else {
                $depth--;
                $pos = $nextClose + strlen($tag) + 3;

                if ($depth === 0) {
                    return substr($html, $start, $pos - $start);
                }
            }
        }

        // Self-closing element or parse failure — return tag itself
        $end = strpos($html, '>', $start);
        return $end !== false ? substr($html, $start, $end - $start + 1) : null;
    }

    protected static function stripTags(string $html): string
    {
        return trim(strip_tags($html));
    }

    /**
     * Check if two HTML fragments have the same structure (same tag tree).
     */
    protected static function structureSame(string $a, string $b): bool
    {
        $tagsA = [];
        $tagsB = [];

        preg_match_all('/<\/?[a-z][a-z0-9]*/i', $a, $mA);
        preg_match_all('/<\/?[a-z][a-z0-9]*/i', $b, $mB);

        return $mA[0] === $mB[0];
    }
}
