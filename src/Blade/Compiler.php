<?php

declare(strict_types=1);

namespace Libxa\Blade;

/**
 * Blade Compiler
 *
 * Converts .blade.php template syntax into executable PHP.
 */
class Compiler
{
    protected array $sections  = [];
    protected array $layouts   = [];
    protected array $customDirectives = [];

    /**
     * Main compile entry
     */
    public function compile(string $source): string
    {
        $result = $source;

        $result = $this->compileComments($result);
        $result = $this->compileRawEcho($result);
        $result = $this->compileEcho($result);
        $result = $this->compileInheritance($result);
        $result = $this->compileIncludes($result);
        $result = $this->compileComponents($result);
        $result = $this->compileFrontendDirectives($result);
        $result = $this->compileControlStructures($result);
        $result = $this->compileAuth($result);
        $result = $this->compileEnv($result);
        $result = $this->compileCustomDirectives($result);
        $result = $this->compileMisc($result);
        $result = $this->compilePhpTags($result);

        return $result;
    }

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

    /**
     * Compile template inheritance: @extends, @section, @endsection, @yield, @parent
     *
     * Strategy: we wrap the whole compiled output in PHP that:
     *  1. Records all @section captures into $__sections[]
     *  2. At the very end, renders the parent layout file via the BladeEngine
     */
    protected function compileInheritance(string $source): string
    {
        // @extends('layouts.app')  →  sets $__layout and starts buffering
        $source = preg_replace_callback(
            '/@extends\([\'"](.+?)[\'"]\)/',
            function (array $m): string {
                $layout = addslashes($m[1]);
                return "<?php \$__layout = '{$layout}'; \$__sections = []; ob_start(); ?>";
            },
            $source
        );

        // @section('name', 'inline value')  →  direct assignment
        $source = preg_replace_callback(
            '/@section\([\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\)/',
            function (array $m): string {
                $name  = addslashes($m[1]);
                $value = addslashes($m[2]);
                return "<?php \$__sections['{$name}'] = '{$value}'; ?>";
            },
            $source
        );

        // @section('name')  →  start buffering
        $source = preg_replace_callback(
            '/@section\([\'"](.+?)[\'"]\)/',
            function (array $m): string {
                $name = addslashes($m[1]);
                return "<?php \$__currentSection = '{$name}'; ob_start(); ?>";
            },
            $source
        );

        // @endsection  →  store buffered content
        $source = str_replace(
            '@endsection',
            "<?php \$__sections[\$__currentSection] = ob_get_clean(); ?>",
            $source
        );

        // @yield('name', 'default')  →  echo the section or default
        $source = preg_replace_callback(
            '/@yield\([\'"](.+?)[\'"](?:\s*,\s*[\'"](.+?)[\'"])?\)/',
            function (array $m): string {
                $name    = addslashes($m[1]);
                $default = isset($m[2]) ? addslashes($m[2]) : '';
                return "<?= \$__sections['{$name}'] ?? '{$default}' ?>";
            },
            $source
        );

        // @parent  →  placeholder (not commonly supported in simple engines)
        $source = str_replace('@parent', '', $source);

        // At end: if a layout was declared, discard the child's top-level output
        // and instead render the layout, passing $__sections in scope.
        // We append a footer only if @extends was used.
        if (str_contains($source, '$__layout')) {
            $footer = <<<'PHP'

<?php
ob_end_clean(); // discard child output outside sections
echo app('blade')->renderWithSections($__layout, $__sections, get_defined_vars());
?>
PHP;
            $source .= $footer;
        }

        return $source;
    }

    protected function compileIncludes(string $source): string
    {
        $source = preg_replace_callback(
            '/@include\([\'"](.+?)[\'"]\s*(?:,\s*(.+?))?\)/',
            function (array $m): string {
                $view = $m[1];
                $data = isset($m[2]) ? "array_merge(get_defined_vars(), {$m[2]})" : 'get_defined_vars()';
                return "<?php echo app('blade')->render('$view', $data); ?>";
            },
            $source
        );

        return $source;
    }

    protected function compileComponents(string $source): string
    {
        $source = preg_replace_callback(
            '/@component\([\'"](.+?)[\'"]\s*(?:,\s*(.+?))?\)/',
            function (array $m): string {
                $view = $m[1];
                $data = $m[2] ?? '[]';
                return "<?php ob_start(); \$__componentData = $data; \$__componentView = '$view'; ?>";
            },
            $source
        );

        $source = str_replace(
            '@endcomponent',
            "<?php \$__componentSlot = ob_get_clean(); echo app('blade')->render(\$__componentView, array_merge(\$__componentData, ['slot' => \$__componentSlot])); ?>",
            $source
        );

        return $source;
    }

    protected function compileFrontendDirectives(string $source): string
    {
        $source = preg_replace_callback(
            '/@react\([\'"](.+?)[\'"]\s*(?:,\s*(.+?))?\)/',
            fn($m) => "<div data-Libxa-react=\"{$m[1]}\" data-props=\"<?= htmlspecialchars(json_encode(" . ($m[2] ?? '[]') . "), ENT_QUOTES) ?>\"></div>",
            $source
        );

        $source = preg_replace_callback(
            '/@vite\((.+?)\)/',
            fn($m) => "<?php echo vite({$m[1]}); ?>",
            $source
        );

        $source = str_replace('@inertia', "<div id=\"app\" data-page=\"<?= htmlspecialchars(json_encode(\$page ?? []), ENT_QUOTES) ?>\"></div>", $source);

        return $source;
    }

    protected function compileControlStructures(string $source): string
    {
        $expr = '\(((?:[^()]++|\([^()]*\))*)\)';

        $mappings = [
            '/@if\s*' . $expr . '/'      => "<?php if($1): ?>",
            '/@elseif\s*' . $expr . '/'  => "<?php elseif($1): ?>",
            '/@else/'                    => "<?php else: ?>",
            '/@endif/'                   => "<?php endif; ?>",
            '/@foreach\s*' . $expr . '/' => "<?php foreach($1): ?>",
            '/@endforeach/'              => "<?php endforeach; ?>",
            '/@for\s*' . $expr . '/'     => "<?php for($1): ?>",
            '/@endfor/'                  => "<?php endfor; ?>",
            '/@while\s*' . $expr . '/'   => "<?php while($1): ?>",
            '/@endwhile/'                => "<?php endwhile; ?>",
            '/@php/'                     => "<?php",
            '/@endphp/'                  => "?>",
        ];

        foreach ($mappings as $pattern => $replace) {
            $source = preg_replace($pattern, $replace, $source);
        }

        return $source;
    }

    protected function compileAuth(string $source): string
    {
        $source = str_replace('@auth',     "<?php if(auth()->check()): ?>",  $source);
        $source = str_replace('@endauth',  "<?php endif; ?>",                $source);
        $source = str_replace('@guest',    "<?php if(!auth()->check()): ?>", $source);
        $source = str_replace('@endguest', "<?php endif; ?>",                $source);
        return $source;
    }

    protected function compileEnv(string $source): string
    {
        $source = preg_replace('/@env\([\'"](.+?)[\'"]\)/', "<?php if(app()->env('APP_ENV') === '$1'): ?>", $source);
        $source = str_replace('@endenv', "<?php endif; ?>", $source);
        $source = preg_replace('/@production/', "<?php if(app()->env('APP_ENV') === 'production'): ?>", $source);
        $source = str_replace('@endproduction', "<?php endif; ?>", $source);
        return $source;
    }

    protected function compileMisc(string $source): string
    {
        $source = str_replace('@csrf', '<input type="hidden" name="_token" value="' . "<?= csrf_token() ?>" . '">', $source);
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
        foreach ($this->customDirectives as $name => $handler) {
            $source = preg_replace_callback(
                "/@{$name}(?:\((.+?)\))?/",
                fn($m) => $handler($m[1] ?? null),
                $source
            );
        }
        return $source;
    }
}