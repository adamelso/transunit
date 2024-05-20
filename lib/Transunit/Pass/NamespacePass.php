<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;
use Transunit\Pass;

class NamespacePass implements Pass
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

        $this->moveToNamespace($node);
        $this->importSubjectClass($node);
        $this->importMockingLibraryClasses($node);
    }

    /**
     * namespace tests\unit\Foo\Bar;
     */
    private function moveToNamespace(Namespace_ $node): void
    {
        $ns = $node->name->getParts();

        if ($ns[0] === 'spec') {
            array_shift($ns);
            $ns = array_merge(['tests', 'unit'], $ns);
        }

        $node->name = new Name($ns);
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

        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Class_) {
                $testClassname = $stmt->name->toString();
                $ns[] = substr($testClassname, 0, -4);

                break;
            }
        }

        $use = new Node\Stmt\Use_([
            new Node\Stmt\UseUse(new Name($fcqn)),
        ]);

        array_unshift($node->stmts, $use);
    }

    private function importMockingLibraryClasses(Namespace_ $node): void
    {
        array_unshift($node->stmts, new Node\Stmt\Use_([
            new Node\Stmt\UseUse(new Name('Prophecy\Prophecy\ObjectProphecy')),
        ]));

        array_unshift($node->stmts, new Node\Stmt\Use_([
            new Node\Stmt\UseUse(new Name('Prophecy\PhpUnit\ProphecyTrait')),
        ]));

        array_unshift($node->stmts, new Node\Stmt\Use_([
            new Node\Stmt\UseUse(new Name('PHPUnit\Framework\TestCase')),
        ]));
    }
}
