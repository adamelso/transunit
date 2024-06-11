<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;
use Transunit\Pass;

/**
 * ```
 *   namespace test\unit\Foo\Bar;
 *
 * + use Foo\Bar\TestSubject;
 *
 *   class TestSubjectSpec extends ObjectBehaviour
 *   {
 * ```
 */
class ImportSubjectClassPass implements Pass
{
    public function find(NodeFinder $nodeFinder, $ast): array
    {
        return [$nodeFinder->findFirstInstanceOf($ast, Namespace_::class)];
    }

    public function rewrite(Node $node): void
    {
        if (!$node instanceof Namespace_) {
            return;
        }

        $this->importSubjectClass($node);
    }

    /**
     * use Foo\Bar\Baz;
     */
    private function importSubjectClass(Namespace_ $node): void
    {
        $ns = $node->name->getParts();

        if ($ns[0] === 'tests' && $ns[1] === 'unit') {
            array_shift($ns);
            array_shift($ns);
        } elseif ($ns[0] === 'spec') {
            array_shift($ns);
        }

        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Class_) {
                $testClassname = $stmt->name->toString();
                $ns[] = substr($testClassname, 0, -4);

                break;
            }
        }

        $fcqn = implode('\\', $ns);

        // check if already imported
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Use_) {
                foreach ($stmt->uses as $use) {
                    if ($use->name->toString() === $fcqn) {
                        return;
                    }
                }
            }
        }

        $use = new Node\Stmt\Use_([
            new Node\Stmt\UseUse(new Name($fcqn)),
        ]);

        array_unshift($node->stmts, $use);
    }
}
