<?php

declare(strict_types=1);

namespace Libxa\Blade;

/**
 * Blade Compiler
 *
 * Converts .blade.php template syntax into executable PHP.
 *
 * Stability notes (see CHANGES.md for the full write-up):
 *  - Directive arguments are now extracted with a string-aware, arbitrarily
 *    deep balanced-parentheses scanner (compileDirective/findMatchingParen)
 *    instead of a fixed-depth regex. Nested function calls / arrays inside
 *    @if, @foreach, @include, etc. no longer truncate or silently mis-compile.
 *  - @verbatim / @endverbatim protects raw {{ }} blocks (needed for Vue/
 *    JS template literals used by the frontend adapters) from compilation.
 *  - View/component names are always addslashes()'d before being embedded
 *    in generated PHP string literals, so a stray quote in a view name can
 *    no longer produce a fatal parse error in the compiled cache file.
 *  - $__sections is always initialized, so @section()/@endsection works
 *    even in a view that doesn't @extends anything.
 *  - @push / @endpush / @prepend are now actually compiled (previously
 *    documented but never wired up — they rendered as literal text).
 */
class Compiler
{
    protected array $customDirectives = [];

    /** Storage for @verbatim blocks pulled out before compilation. */
    protected array $verbatimBlocks = [];

    /**
     * Main compile entry
     */
    public function compile(string $source): string
    {
        $this->verbatimBlocks = [];

        $result = $source;

        $result = $this->extractVerbatim($result);
        $result = $this->compileComments($result);
        $result = $this->compileRawEcho($result);
        $result = $this->compileEcho($result);
        $result = $this->compileInheritance($result);
        $result = $this->compileIncludes($result);
        $result = $this->compileComponents($result);
        $result = $this->compileStacks($result);
        $result = $this->compileFrontendDirectives($result);
        $result = $this->compileControlStructures($result);
        $result = $this->compileAuth($result);
        $result = $this->compileEnv($result);
        $result = $this->compileCustomDirectives($result);
        $result = $this->compileMisc($result);
        $result = $this->compilePhpTags($result);
        $result = $this->restoreVerbatim($result);

        // Always guarantee $__sections exists so bare @section/@endsection
        // (used without @extends, e.g. in a reusable partial) never hits
        // an undefined-variable warning.
        $result = "<?php \$__sections = \$__sections ?? []; \$__pushStack = \$__pushStack ?? []; \$__sectionStack = \$__sectionStack ?? []; ?>" . $result;

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────
    //  @verbatim protection
    // ─────────────────────────────────────────────────────────────────

    protected function extractVerbatim(string $source): string
    {
        return preg_replace_callback(
            '/@verbatim\s*(.*?)\s*@endverbatim/s',
            function (array $m): string {
                $key = "\0__VERBATIM_" . count($this->verbatimBlocks) . "__\0";
                $this->verbatimBlocks[$key] = $m[1];
                return $key;
            },
            $source
        );
    }

    protected function restoreVerbatim(string $source): string
    {
        if (! $this->verbatimBlocks) {
            return $source;
        }

        return strtr($source, $this->verbatimBlocks);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Balanced, string-aware directive-argument parser
    // ─────────────────────────────────────────────────────────────────

    /**
     * Find every "@name(...)" occurrence in $source and replace it via
     * $replacer(?string $rawArgs). Unlike a fixed-depth regex, this
     * correctly handles:
     *   - arbitrarily nested parentheses: @if(in_array($x, [f(1, g(2))]))
     *   - parentheses inside string literals: @if($x === 'a)b')
     *   - escaped quotes inside string literals: @if($x === 'it\'s')
     *
     * If $requireParens is false, a bare "@name" with no following "(...)"
     * is also matched and $replacer receives null for the args.
     */
    protected function compileDirective(
        string $source,
        string $name,
        callable $replacer,
        bool $requireParens = true
    ): string {
        $len    = strlen($source);
        $needle = '@' . $name;
        $out    = '';
        $cursor = 0;

        while (($pos = strpos($source, $needle, $cursor)) !== false) {
            $after = $pos + strlen($needle);
            $boundaryOk = $after >= $len || ! (ctype_alnum($source[$after]) || $source[$after] === '_');

            if (! $boundaryOk) {
                $out .= substr($source, $cursor, $after - $cursor);
                $cursor = $after;
                continue;
            }

            $out .= substr($source, $cursor, $pos - $cursor);

            // Skip whitespace between the directive name and '('
            $i = $after;
            while ($i < $len && ($source[$i] === ' ' || $source[$i] === "\t")) {
                $i++;
            }

            if ($i >= $len || $source[$i] !== '(') {
                if ($requireParens) {
                    // Not actually a call — leave the literal text untouched.
                    $out .= substr($source, $pos, $after - $pos);
                    $cursor = $after;
                    continue;
                }

                $out   .= $replacer(null);
                $cursor = $after;
                continue;
            }

            $close = $this->findMatchingParen($source, $i);
            if ($close === null) {
                // Unbalanced parens — bail out safely rather than
                // corrupting the rest of the file. Leave as literal text.
                $out .= substr($source, $pos, $after - $pos);
                $cursor = $after;
                continue;
            }

            $args = substr($source, $i + 1, $close - $i - 1);
            $out .= $replacer($args);
            $cursor = $close + 1;
        }

        $out .= substr($source, $cursor);

        return $out;
    }

    /**
     * Given the index of an opening '(' in $source, return the index of
     * its matching ')', respecting nested parens and quoted strings.
     * Returns null if unbalanced.
     */
    protected function findMatchingParen(string $source, int $openIndex): ?int
    {
        $len      = strlen($source);
        $depth    = 0;
        $inString = null; // null | "'" | '"'

        for ($j = $openIndex; $j < $len; $j++) {
            $ch = $source[$j];

            if ($inString !== null) {
                if ($ch === '\\') {
                    $j++; // skip escaped char
                    continue;
                }
                if ($ch === $inString) {
                    $inString = null;
                }
                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $inString = $ch;
                continue;
            }

            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    return $j;
                }
            }
        }

        return null;
    }

    /**
     * Split a comma-separated top-level argument list (respecting nested
     * parens/brackets/braces and quoted strings) into individual trimmed
     * argument strings.
     */
    protected function splitArgs(string $args): array
    {
        $args = trim($args);
        if ($args === '') {
            return [];
        }

        $parts    = [];
        $depth    = 0;
        $inString = null;
        $current  = '';
        $len      = strlen($args);

        for ($i = 0; $i < $len; $i++) {
            $ch = $args[$i];

            if ($inString !== null) {
                $current .= $ch;
                if ($ch === '\\') {
                    if ($i + 1 < $len) {
                        $current .= $args[++$i];
                    }
                    continue;
                }
                if ($ch === $inString) {
                    $inString = null;
                }
                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $inString = $ch;
                $current .= $ch;
                continue;
            }

            if ($ch === '(' || $ch === '[' || $ch === '{') {
                $depth++;
                $current .= $ch;
                continue;
            }

            if ($ch === ')' || $ch === ']' || $ch === '}') {
                $depth--;
                $current .= $ch;
                continue;
            }

            if ($ch === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $ch;
        }

        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    /**
     * Strip one layer of matching quotes from a literal string argument,
     * e.g. "'users.index'" -> "users.index". Returns null if $arg is not
     * a simple quoted literal (i.e. it's a PHP expression/variable).
     */
    protected function unquote(string $arg): ?string
    {
        $arg = trim($arg);
        $n   = strlen($arg);

        if ($n >= 2 && ($arg[0] === "'" || $arg[0] === '"') && $arg[$n - 1] === $arg[0]) {
            $inner = substr($arg, 1, -1);
            return stripcslashes($inner);
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Echoes / comments
    // ─────────────────────────────────────────────────────────────────

    protected function compileEcho(string $source): string
    {
        return preg_replace(
            '/\{\{\s*(.+?)\s*\}\}/s',
            "<?= htmlspecialchars((string) ($1), ENT_QUOTES, 'UTF-8') ?>",
            $source
        );
    }

    protected function compileRawEcho(string $source): string
    {
        return preg_replace(
            '/\{!!\s*(.+?)\s*!!\}/s',
            "<?= $1 ?>",
            $source
        );
    }

    protected function compileComments(string $source): string
    {
        return preg_replace('/\{\{--(.+?)--\}\}/s', "<?php /* $1 */ ?>", $source);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Inheritance: @extends, @section, @endsection, @yield, @parent
    // ─────────────────────────────────────────────────────────────────

    protected function compileInheritance(string $source): string
    {
        $usesExtends = false;

        $source = $this->compileDirective($source, 'extends', function (?string $args) use (&$usesExtends): string {
            $usesExtends = true;
            $literal = $this->unquote((string) $args);
            if ($literal !== null) {
                $layout = addslashes($literal);
                return "<?php \$__layout = '{$layout}'; \$__sections = \$__sections ?? []; ob_start(); ?>";
            }
            return "<?php \$__layout = ({$args}); \$__sections = \$__sections ?? []; ob_start(); ?>";
        });

        // @section(...) — either "@section('name', 'inline')" (assignment)
        // or "@section('name')" ... "@endsection" (buffered, stack-based
        // so nested sections don't clobber $__currentSection).
        $source = $this->compileDirective($source, 'section', function (?string $args): string {
            $parts = $this->splitArgs((string) $args);
            $name  = $this->unquote($parts[0] ?? '') ?? trim((string) ($parts[0] ?? ''));

            if (count($parts) >= 2) {
                $nameExpr = addslashes($name);
                return "<?php \$__sections['{$nameExpr}'] = ({$parts[1]}); ?>";
            }

            $nameExpr = addslashes($name);
            return "<?php \$__sectionStack[] = '{$nameExpr}'; ob_start(); ?>";
        });

        $source = str_replace(
            '@endsection',
            "<?php \$__sections[array_pop(\$__sectionStack)] = ob_get_clean(); ?>",
            $source
        );
        // @show = end section AND immediately echo it in place.
        $source = str_replace(
            '@show',
            "<?php \$__lastSection = array_pop(\$__sectionStack); \$__sections[\$__lastSection] = ob_get_clean(); echo \$__sections[\$__lastSection]; ?>",
            $source
        );

        $source = $this->compileDirective($source, 'yield', function (?string $args): string {
            $parts       = $this->splitArgs((string) $args);
            $name        = $this->unquote($parts[0] ?? '') ?? ($parts[0] ?? '');
            $nameExpr    = addslashes($name);
            $defaultExpr = isset($parts[1]) ? "({$parts[1]})" : "''";
            return "<?= \$__sections['{$nameExpr}'] ?? {$defaultExpr} ?>";
        });

        // @hasSection('name') / @sectionMissing('name')
        // NOTE: this was previously undefined even though the LibxaStack
        // starter kit's own default layout (layouts/app.blade.php) uses
        // @hasSection — meaning that layout had a fatal PHP parse error
        // in its compiled cache file from day one. See CHANGES.md.
        $source = $this->compileDirective($source, 'hasSection', function (?string $args): string {
            $parts = $this->splitArgs((string) $args);
            $name  = $this->unquote($parts[0] ?? '') ?? ($parts[0] ?? '');
            $nameExpr = addslashes($name);
            return "<?php if (! empty(\$__sections['{$nameExpr}'])): ?>";
        });
        $source = preg_replace('/@endHasSection\b/i', "<?php endif; ?>", $source);

        $source = $this->compileDirective($source, 'sectionMissing', function (?string $args): string {
            $parts = $this->splitArgs((string) $args);
            $name  = $this->unquote($parts[0] ?? '') ?? ($parts[0] ?? '');
            $nameExpr = addslashes($name);
            return "<?php if (empty(\$__sections['{$nameExpr}'])): ?>";
        });
        $source = preg_replace('/@endSectionMissing\b/i', "<?php endif; ?>", $source);

        // @parent — not supported by this lightweight engine; strip it
        // rather than leaving literal "@parent" text in the output.
        $source = str_replace('@parent', '', $source);

        if ($usesExtends) {
            $footer = <<<'PHP'

<?php
// Discard the child template's top-level output (only @section content
// captured into $__sections is kept); render the resolved layout.
// Cleans up ALL nested ob levels opened while compiling this child view
// (sections/components left unclosed by a thrown exception would
// otherwise leak buffer levels into the next request on a persistent
// worker such as src/Reactive/WsServer.php).
while (ob_get_level() > $__obBaseline) { ob_end_clean(); }
echo app('blade')->renderWithSections($__layout, $__sections, get_defined_vars());
?>
PHP;
            $source = "<?php \$__obBaseline = ob_get_level(); ?>" . $source . $footer;
        }

        return $source;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Includes / components
    // ─────────────────────────────────────────────────────────────────

    protected function compileIncludes(string $source): string
    {
        $source = $this->compileDirective($source, 'includeIf', function (?string $args): string {
            $parts = $this->splitArgs((string) $args);
            $view  = $this->viewExpr($parts[0] ?? "''");
            $data  = $this->includeDataExpr($parts[1] ?? null);
            return "<?php if (app('blade')->exists({$view})) { echo app('blade')->render({$view}, {$data}); } ?>";
        });

        $source = $this->compileDirective($source, 'includeWhen', function (?string $args): string {
            $parts = $this->splitArgs((string) $args);
            $cond  = $parts[0] ?? 'false';
            $view  = $this->viewExpr($parts[1] ?? "''");
            $data  = $this->includeDataExpr($parts[2] ?? null);
            return "<?php if ({$cond}) { echo app('blade')->render({$view}, {$data}); } ?>";
        });

        $source = $this->compileDirective($source, 'include', function (?string $args): string {
            $parts = $this->splitArgs((string) $args);
            $view  = $this->viewExpr($parts[0] ?? "''");
            $data  = $this->includeDataExpr($parts[1] ?? null);
            return "<?php echo app('blade')->render({$view}, {$data}); ?>";
        });

        $source = $this->compileDirective($source, 'each', function (?string $args): string {
            $parts     = $this->splitArgs((string) $args);
            $view      = $this->viewExpr($parts[0] ?? "''");
            $itemsExpr = $parts[1] ?? '[]';
            $varName   = $this->unquote($parts[2] ?? "'item'") ?? 'item';
            $varExpr   = addslashes($varName);
            $emptyView = isset($parts[3]) ? $this->viewExpr($parts[3]) : null;

            $php  = "<?php \$__eachItems = ({$itemsExpr}); ";
            $php .= "if (empty(\$__eachItems)) { ";
            $php .= $emptyView ? "echo app('blade')->render({$emptyView}, []); " : '';
            $php .= "} else { foreach (\$__eachItems as \$__eachKey => \$__eachVal) { ";
            $php .= "echo app('blade')->render({$view}, ['{$varExpr}' => \$__eachVal, 'key' => \$__eachKey]); ";
            $php .= "} } ?>";

            return $php;
        });

        return $source;
    }

    protected function viewExpr(string $rawArg): string
    {
        $literal = $this->unquote($rawArg);
        if ($literal !== null) {
            return "'" . addslashes($literal) . "'";
        }
        // A dynamic expression (variable/function) — wrap defensively.
        return "({$rawArg})";
    }

    protected function includeDataExpr(?string $rawArg): string
    {
        if ($rawArg === null || trim($rawArg) === '') {
            return 'get_defined_vars()';
        }
        return "array_merge(get_defined_vars(), ({$rawArg}))";
    }

    protected function compileComponents(string $source): string
    {
        $source = $this->compileDirective($source, 'component', function (?string $args): string {
            $parts = $this->splitArgs((string) $args);
            $view  = $this->viewExpr($parts[0] ?? "''");
            $data  = $parts[1] ?? '[]';
            return "<?php ob_start(); \$__componentData = ({$data}); \$__componentView = {$view}; ?>";
        });

        $source = str_replace(
            '@endcomponent',
            "<?php \$__componentSlot = ob_get_clean(); echo app('blade')->render(\$__componentView, array_merge(\$__componentData, ['slot' => \$__componentSlot])); ?>",
            $source
        );

        $source = $this->compileDirective($source, 'slot', function (?string $args): string {
            $name = $this->unquote((string) $args) ?? trim((string) $args, " \t'\"");
            $expr = addslashes($name);
            return "<?php \$__slotName = '{$expr}'; ob_start(); ?>";
        });

        $source = str_replace(
            '@endslot',
            "<?php \$__componentData[\$__slotName] = ob_get_clean(); ?>",
            $source
        );

        return $source;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Stacks: @push / @endpush / @prepend / @endprepend / @stack
    // ─────────────────────────────────────────────────────────────────

    protected function compileStacks(string $source): string
    {
        $source = $this->compileDirective($source, 'push', function (?string $args): string {
            $name = $this->unquote((string) $args) ?? (string) $args;
            $expr = addslashes($name);
            return "<?php ob_start(); \$__pushStack[] = '{$expr}'; ?>";
        });
        $source = str_replace(
            '@endpush',
            "<?php \\Libxa\\Blade\\BladeStack::push(array_pop(\$__pushStack), ob_get_clean()); ?>",
            $source
        );

        $source = $this->compileDirective($source, 'prepend', function (?string $args): string {
            $name = $this->unquote((string) $args) ?? (string) $args;
            $expr = addslashes($name);
            return "<?php ob_start(); \$__pushStack[] = '{$expr}'; ?>";
        });
        $source = str_replace(
            '@endprepend',
            "<?php \\Libxa\\Blade\\BladeStack::prepend(array_pop(\$__pushStack), ob_get_clean()); ?>",
            $source
        );

        return $source;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Frontend adapters
    // ─────────────────────────────────────────────────────────────────

    protected function compileFrontendDirectives(string $source): string
    {
        $source = $this->compileDirective($source, 'react', function (?string $args): string {
            $parts         = $this->splitArgs((string) $args);
            $componentExpr = $this->viewExpr($parts[0] ?? "''");
            $propsExpr     = $parts[1] ?? '[]';
            return "<div data-Libxa-react=\"<?= htmlspecialchars((string) ({$componentExpr}), ENT_QUOTES, 'UTF-8') ?>\" data-props=\"<?= htmlspecialchars(json_encode({$propsExpr}), ENT_QUOTES, 'UTF-8') ?>\"></div>";
        });

        $source = $this->compileDirective($source, 'vite', function (?string $args): string {
            return "<?php echo vite({$args}); ?>";
        });

        $source = str_replace('@inertia', "<div id=\"app\" data-page=\"<?= htmlspecialchars(json_encode(\$page ?? []), ENT_QUOTES, 'UTF-8') ?>\"></div>", $source);

        return $source;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Control structures
    // ─────────────────────────────────────────────────────────────────

    protected function compileControlStructures(string $source): string
    {
        $source = $this->compileDirective($source, 'forelse', fn(?string $a) => "<?php \$__empty_1 = true; foreach ({$a}): \$__empty_1 = false; ?>");
        $source = preg_replace('/@empty\b(?!\()/', "<?php endforeach; if (\$__empty_1): ?>", $source);
        $source = preg_replace('/@endforelse\b/', "<?php endif; ?>", $source);

        $source = $this->compileDirective($source, 'if', fn(?string $a) => "<?php if ({$a}): ?>");
        $source = $this->compileDirective($source, 'elseif', fn(?string $a) => "<?php elseif ({$a}): ?>");
        $source = preg_replace('/@else\b/', "<?php else: ?>", $source);
        $source = preg_replace('/@endif\b/', "<?php endif; ?>", $source);

        $source = $this->compileDirective($source, 'unless', fn(?string $a) => "<?php if (! ({$a})): ?>");
        $source = preg_replace('/@endunless\b/', "<?php endif; ?>", $source);

        $source = $this->compileDirective($source, 'isset', fn(?string $a) => "<?php if (isset({$a})): ?>");
        $source = preg_replace('/@endisset\b/', "<?php endif; ?>", $source);

        $source = $this->compileDirective($source, 'foreach', fn(?string $a) => "<?php foreach ({$a}): ?>");
        $source = preg_replace('/@endforeach\b/', "<?php endforeach; ?>", $source);

        $source = $this->compileDirective($source, 'for', fn(?string $a) => "<?php for ({$a}): ?>");
        $source = preg_replace('/@endfor\b(?!each|else)/', "<?php endfor; ?>", $source);

        $source = $this->compileDirective($source, 'while', fn(?string $a) => "<?php while ({$a}): ?>");
        $source = preg_replace('/@endwhile\b/', "<?php endwhile; ?>", $source);

        $source = preg_replace('/@php\b(?!\()/', "<?php", $source);
        $source = preg_replace('/@endphp\b/', "?>", $source);
        // @php(...) inline form
        $source = $this->compileDirective($source, 'php', fn(?string $a) => "<?php {$a}; ?>");

        $source = $this->compileDirective($source, 'error', function (?string $a): string {
            return "<?php if (errors()->has({$a})): \$message = errors()->first({$a}); ?>";
        });
        $source = preg_replace('/@enderror\b/', "<?php endif; unset(\$message); ?>", $source);

        return $source;
    }

    protected function compileAuth(string $source): string
    {
        $source = str_replace('@auth',     "<?php if (auth()->check()): ?>",   $source);
        $source = str_replace('@endauth',  "<?php endif; ?>",                  $source);
        $source = str_replace('@guest',    "<?php if (! auth()->check()): ?>", $source);
        $source = str_replace('@endguest', "<?php endif; ?>",                  $source);
        return $source;
    }

    protected function compileEnv(string $source): string
    {
        $source = $this->compileDirective($source, 'env', function (?string $a): string {
            $env = $this->unquote((string) $a) ?? (string) $a;
            return "<?php if (app()->env('APP_ENV') === '" . addslashes($env) . "'): ?>";
        });
        $source = str_replace('@endenv', "<?php endif; ?>", $source);
        $source = preg_replace('/@production\b/', "<?php if (app()->env('APP_ENV') === 'production'): ?>", $source);
        $source = str_replace('@endproduction', "<?php endif; ?>", $source);
        return $source;
    }

    protected function compileMisc(string $source): string
    {
        $source = str_replace('@csrf', '<input type="hidden" name="_token" value="<?= csrf_token() ?>">', $source);

        $source = $this->compileDirective($source, 'method', function (?string $a): string {
            $method = strtoupper($this->unquote((string) $a) ?? (string) $a);
            return '<input type="hidden" name="_method" value="' . addslashes($method) . '">';
        });

        $source = $this->compileDirective($source, 'session', function (?string $a): string {
            return "<?php if (session({$a})): ?>";
        });
        $source = str_replace('@endsession', "<?php endif; ?>", $source);

        $source = $this->compileDirective($source, 'old', function (?string $a): string {
            $parts   = $this->splitArgs((string) $a);
            $key     = $parts[0] ?? "''";
            $default = $parts[1] ?? "''";
            return "<?= htmlspecialchars((string) old({$key}, {$default}), ENT_QUOTES, 'UTF-8') ?>";
        });

        $source = $this->compileDirective($source, 'json', fn(?string $a) => "<?= json_encode({$a}, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>");

        $source = $this->compileDirective($source, 'stack', function (?string $a): string {
            $name = $this->unquote((string) $a) ?? (string) $a;
            return "<?= \\Libxa\\Blade\\BladeStack::get('" . addslashes($name) . "') ?>";
        });

        $source = $this->compileDirective($source, 'checked', fn(?string $a) => "<?= ({$a}) ? 'checked' : '' ?>");
        $source = $this->compileDirective($source, 'selected', fn(?string $a) => "<?= ({$a}) ? 'selected' : '' ?>");
        $source = $this->compileDirective($source, 'disabled', fn(?string $a) => "<?= ({$a}) ? 'disabled' : '' ?>");
        $source = $this->compileDirective($source, 'readonly', fn(?string $a) => "<?= ({$a}) ? 'readonly' : '' ?>");
        $source = $this->compileDirective($source, 'required', fn(?string $a) => "<?= ({$a}) ? 'required' : '' ?>");

        return $source;
    }

    protected function compilePhpTags(string $source): string
    {
        return $source;
    }

    public function directive(string $name, \Closure $handler): void
    {
        $this->customDirectives[$name] = $handler;
    }

    protected function compileCustomDirectives(string $source): string
    {
        // Sort by name length descending so longer names (e.g. 'livewireScripts')
        // are matched before shorter prefix names (e.g. 'livewire').
        $directives = $this->customDirectives;
        uksort($directives, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($directives as $name => $handler) {
            $source = $this->compileDirective(
                $source,
                $name,
                fn(?string $args) => (string) $handler($args),
                requireParens: false
            );
        }

        return $source;
    }
}
