<?php

declare(strict_types=1);

namespace Tests\Feature;

use PhpParser\Node;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * Guards the framework's PSR-4 contract.
 *
 * Several files used to declare more than one class — Attributes/Route.php
 * held six attribute classes, Support/Str.php hid StringableProxy,
 * Atlas/QueryBuilder.php hid RawExpression, and Container/ContextGraph.php
 * declared a *second* copy of ContextualBindingBuilder. Under PSR-4 the
 * autoloader can never find those extra classes, so referencing one produced
 * either "Class not found" or — for the duplicate — a hard
 * "Cannot redeclare class" fatal as soon as both files happened to load.
 *
 * Both failure modes are silent until the exact code path runs in production,
 * which is why they survived so long. This test makes them loud.
 */
class AutoloadingTest extends TestCase
{
    private const SRC = __DIR__ . '/../../src';

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    private function declarationsPerFile(): iterable
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::SRC, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $ast = $parser->parse((string) file_get_contents($file->getPathname()));

            if ($ast === null) {
                continue;
            }

            $found = [];

            // Only top-level declarations count: anything inside a heredoc
            // (the make:* command stubs) is just a string. Names are collected
            // fully qualified — Atlas\Attributes\BelongsTo and
            // Atlas\Relations\BelongsTo are two different, legitimate classes.
            $collect = function (array $nodes, string $namespace) use (&$collect, &$found): void {
                foreach ($nodes as $node) {
                    if ($node instanceof Node\Stmt\Namespace_) {
                        $collect($node->stmts, $node->name?->toString() ?? '');
                        continue;
                    }

                    if (($node instanceof Node\Stmt\Class_
                        || $node instanceof Node\Stmt\Interface_
                        || $node instanceof Node\Stmt\Trait_
                        || $node instanceof Node\Stmt\Enum_)
                        && $node->name !== null
                    ) {
                        $found[] = ($namespace === '' ? '' : $namespace . '\\') . $node->name->toString();
                    }
                }
            };

            $collect($ast, '');

            yield $file->getPathname() => $found;
        }
    }

    public function test_every_source_file_declares_at_most_its_own_class(): void
    {
        $violations = [];

        foreach ($this->declarationsPerFile() as $path => $declared) {
            $expected = basename($path, '.php');

            $shortNames = array_map(
                static fn(string $fqcn): string => substr((string) strrchr('\\' . $fqcn, '\\'), 1),
                $declared
            );

            $extra = array_values(array_diff($shortNames, [$expected]));

            if ($extra !== []) {
                $relative = str_replace(dirname(self::SRC) . DIRECTORY_SEPARATOR, '', $path);
                $violations[] = $relative . ' also declares ' . implode(', ', $extra);
            }
        }

        $this->assertSame(
            [],
            $violations,
            "These files declare classes the PSR-4 autoloader cannot find:\n  " . implode("\n  ", $violations)
        );
    }

    public function test_no_class_name_is_declared_twice(): void
    {
        $seen = [];

        foreach ($this->declarationsPerFile() as $path => $declared) {
            foreach ($declared as $name) {
                $seen[$name][] = $path;
            }
        }

        $duplicates = array_filter($seen, static fn(array $paths): bool => count($paths) > 1);

        $this->assertSame(
            [],
            array_keys($duplicates),
            'A class declared in two files is a fatal "Cannot redeclare class" as soon as both load.'
        );
    }

    /**
     * Spot-check the specific classes that were unreachable before, so a
     * regression is reported by name rather than as a generic scan failure.
     */
    public function test_previously_unreachable_classes_now_autoload(): void
    {
        $classes = [
            \Libxa\Router\Attributes\Prefix::class,
            \Libxa\Router\Attributes\Middleware::class,
            \Libxa\Router\Attributes\WsRoute::class,
            \Libxa\Router\Attributes\ApiController::class,
            \Libxa\Router\Attributes\Gate::class,
            \Libxa\Router\Attributes\Throttle::class,
            \Libxa\Atlas\RawExpression::class,
            \Libxa\Container\ContextualBindingBuilder::class,
            \Libxa\Container\ContextGraph::class,
            \Libxa\Support\StringableProxy::class,
            \Libxa\Support\NumberableProxy::class,
            \Libxa\Blade\SharedData::class,
            \Libxa\Events\Event::class,
            \Libxa\Nova\Field::class,
            \Libxa\Atlas\Schema\ColumnDefinition::class,
            \Libxa\Atlas\Schema\ForeignKeyDefinition::class,
            \Libxa\Atlas\AI\AiQueryResult::class,
            \Libxa\Module\ModuleRegistry::class,
            \Libxa\Container\PublishableRegistry::class,
            \Libxa\Async\ParallelException::class,
        ];

        foreach ($classes as $class) {
            $this->assertTrue(
                class_exists($class) || interface_exists($class),
                "[{$class}] is not reachable through the PSR-4 autoloader."
            );
        }
    }
}
