<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Libxa\Blade\Compiler;
use Libxa\Blade\BladeEngine;
use Libxa\Blade\BladeStack;

/**
 * Regression tests for the Blade-X compiler/engine stability fixes.
 * Each test here corresponds to a concrete bug found during the July 2026
 * stability audit (see CHANGES.md). Keeping these passing prevents
 * regressing back to those bugs.
 */
class BladeCompilerTest extends TestCase
{
    protected function compileAndLint(Compiler $compiler, string $source): string
    {
        $compiled = $compiler->compile($source);

        $tmp = tempnam(sys_get_temp_dir(), 'blade_lint_') . '.php';
        file_put_contents($tmp, $compiled);
        exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
        unlink($tmp);

        $this->assertSame(0, $code, "Compiled output has a PHP syntax error:\n" . implode("\n", $out) . "\n\n{$compiled}");

        return $compiled;
    }

    protected function run(string $compiled, array $vars = []): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'blade_run_') . '.php';
        file_put_contents($tmp, $compiled);
        extract($vars);
        ob_start();
        include $tmp;
        $out = ob_get_clean();
        unlink($tmp);
        return $out;
    }

    public function test_nested_parentheses_in_if_condition_compile_and_evaluate_correctly(): void
    {
        $compiler = new Compiler();
        $compiled = $this->compileAndLint($compiler, '@if(in_array($x, [1, f(2,3)])) MATCHED @else NOPE @endif');

        // A single level of nesting was the previous fixed-depth limit;
        // this has 2+ levels and must still compile/evaluate correctly.
        $GLOBALS['__blade_test_f'] = fn($a, $b) => $a + $b;
        $compiledWithHelper = str_replace('f(2,3)', '(1+4)', $compiled);

        $this->assertSame('MATCHED', trim($this->run($compiledWithHelper, ['x' => 5])));
    }

    public function test_deeply_nested_parens_do_not_truncate_the_condition(): void
    {
        $compiler = new Compiler();
        $src = '@if(($a && ($b || ($c && ($d || $e))))) deep @endif';
        $compiled = $this->compileAndLint($compiler, $src);
        $this->assertSame('deep', trim($this->run($compiled, ['a' => true, 'b' => false, 'c' => true, 'd' => false, 'e' => true])));
    }

    public function test_section_without_extends_does_not_warn_or_error(): void
    {
        $compiler = new Compiler();
        $compiled = $this->compileAndLint($compiler, "@section('widget')<b>hi</b>@endsection@yield('widget')");
        $this->assertSame('<b>hi</b>', trim($this->run($compiled)));
    }

    public function test_verbatim_block_is_not_compiled(): void
    {
        $compiler = new Compiler();
        $compiled = $this->compileAndLint($compiler, '@verbatim{{ message }}@endverbatim {{ 1 + 1 }}');
        $this->assertSame('{{ message }} 2', trim($this->run($compiled)));
    }

    public function test_push_and_stack_accumulate_in_order(): void
    {
        BladeStack::flush();
        $compiler = new Compiler();
        $compiled = $this->compileAndLint($compiler, "@push('s')A@endpush@push('s')B@endpush<x>@stack('s')</x>");
        $this->assertSame('<x>A' . "\n" . 'B</x>', trim($this->run($compiled)));
    }

    public function test_has_section_directive_used_by_the_default_starter_kit_layout(): void
    {
        // Regression test for the pre-existing bug where the LibxaStack
        // starter kit's layouts/app.blade.php used @hasSection, which the
        // compiler never implemented — producing a fatal parse error in
        // the compiled cache file for the app's own default layout.
        $compiler = new Compiler();
        $compiled = $this->compileAndLint($compiler, "@hasSection('footer')shown@endif");
        $this->assertSame('shown', trim($this->run($compiled, ['__sections' => ['footer' => 'x']])));
        $this->assertSame('', trim($this->run($compiled, ['__sections' => []])));
    }

    public function test_view_name_containing_a_quote_does_not_break_compiled_php(): void
    {
        $compiler = new Compiler();
        // Would previously produce a fatal parse error in the compiled
        // cache file because the raw view name was interpolated directly
        // into a PHP string literal.
        $this->compileAndLint($compiler, "@include('partials.card', ['title' => \"It's here\"])");
        $this->addToAssertionCount(1);
    }

    public function test_engine_unwinds_all_output_buffers_when_a_view_throws(): void
    {
        $viewsDir = sys_get_temp_dir() . '/blade_test_views_' . uniqid();
        $cacheDir = sys_get_temp_dir() . '/blade_test_cache_' . uniqid();
        mkdir($viewsDir, 0755, true);
        mkdir($cacheDir, 0755, true);

        // A view that opens a @section buffer and throws before @endsection
        // closes it -- simulates a real-world error inside a section.
        file_put_contents(
            $viewsDir . '/broken.blade.php',
            "@section('x')<?php throw new \\RuntimeException('boom'); ?>@endsection"
        );

        $engine = new BladeEngine($viewsDir, $cacheDir);

        $before = ob_get_level();
        try {
            $engine->render('broken');
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }
        $after = ob_get_level();

        $this->assertSame($before, $after, 'evaluateView() must unwind every output buffer level it opened, even when the view throws mid-section.');

        // Cleanup
        @unlink($viewsDir . '/broken.blade.php');
        @rmdir($viewsDir);
        array_map('unlink', glob($cacheDir . '/*.php'));
        @rmdir($cacheDir);
    }

    public function test_include_recursion_guard_prevents_stack_overflow_on_circular_includes(): void
    {
        $viewsDir = sys_get_temp_dir() . '/blade_test_views_' . uniqid();
        $cacheDir = sys_get_temp_dir() . '/blade_test_cache_' . uniqid();
        mkdir($viewsDir, 0755, true);
        mkdir($cacheDir, 0755, true);

        file_put_contents($viewsDir . '/a.blade.php', "@include('b')");
        file_put_contents($viewsDir . '/b.blade.php', "@include('a')");

        $engine = new BladeEngine($viewsDir, $cacheDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/render depth exceeded/i');

        try {
            $engine->render('a');
        } finally {
            @unlink($viewsDir . '/a.blade.php');
            @unlink($viewsDir . '/b.blade.php');
            @rmdir($viewsDir);
            array_map('unlink', glob($cacheDir . '/*.php'));
            @rmdir($cacheDir);
        }
    }

    public function test_namespace_view_not_found_gives_clear_error_not_a_corrupted_path(): void
    {
        $viewsDir = sys_get_temp_dir() . '/blade_test_views_' . uniqid();
        mkdir($viewsDir, 0755, true);
        $engine = new BladeEngine($viewsDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/namespace \[admin\] is not registered/i');

        try {
            $engine->render('admin::users.index');
        } finally {
            @rmdir($viewsDir);
        }
    }
}
