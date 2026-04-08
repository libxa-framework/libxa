<?php

declare(strict_types=1);

namespace Libxa\Module;

use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PhpParser\Node;

/**
 * Namespace Migrator
 * 
 * Uses nikic/php-parser to perform precision AST-based 
 * namespace rewriting in PHP files.
 */
class NamespaceMigrator
{
    protected $parser;
    protected $printer;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->printer = new PrettyPrinter\Standard();
    }

    /**
     * Migrate all PHP files in a directory to a new namespace.
     */
    public function migrateDirectory(string $dir, string $oldNamespace, string $newNamespace): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->migrateFile($file->getPathname(), $oldNamespace, $newNamespace);
            }
        }
    }

    /**
     * Migrate a single PHP file.
     */
    public function migrateFile(string $path, string $oldNamespace, string $newNamespace): void
    {
        $code = file_get_contents($path);

        try {
            $ast = $this->parser->parse($code);
            
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new class($oldNamespace, $newNamespace) extends NodeVisitorAbstract {
                public function __construct(protected string $old, protected string $new) {}

                public function enterNode(Node $node) {
                    // 1. Rewrite Namespace declaration
                    if ($node instanceof Node\Stmt\Namespace_ && $node->name) {
                        $currentNs = (string) $node->name;
                        if (str_starts_with($currentNs, $this->old)) {
                            $node->name = new Node\Name(str_replace($this->old, $this->new, $currentNs));
                        }
                    }

                    // 2. Rewrite Use statements
                    if ($node instanceof Node\Stmt\Use_) {
                        foreach ($node->uses as $use) {
                            $useNs = (string) $use->name;
                            if (str_starts_with($useNs, $this->old)) {
                                $use->name = new Node\Name(str_replace($this->old, $this->new, $useNs));
                            }
                        }
                    }
                    
                    // 3. Rewrite Group Use
                    if ($node instanceof Node\Stmt\GroupUse) {
                        $prefix = (string) $node->prefix;
                        if (str_starts_with($prefix, $this->old)) {
                            $node->prefix = new Node\Name(str_replace($this->old, $this->new, $prefix));
                        }
                    }

                    // 4. Rewrite FQCN in code (e.g. static calls, typehints)
                    if ($node instanceof Node\Name) {
                        $name = (string) $node;
                        if (str_starts_with($name, $this->old)) {
                            return new Node\Name(str_replace($this->old, $this->new, $name));
                        }
                    }
                }
            });

            $ast = $traverser->traverse($ast);
            $newCode = $this->printer->prettyPrintFile($ast);
            
            file_put_contents($path, $newCode);

        } catch (Error $error) {
            // Silently skip files that can't be parsed (maybe not PHP)
        }
    }
}
