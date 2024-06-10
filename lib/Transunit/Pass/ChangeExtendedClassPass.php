<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use Transunit\Pass;

/**
 * ```
 * - class TestSubjectTest extends ObjectBehaviour
 * + class TestSubjectTest extends TestCase
 * {
 * ```
 */
class ChangeExtendedClassPass implements Pass
{
    public function find(NodeFinder $nodeFinder, $ast): array
    {
        return [$nodeFinder->findFirstInstanceOf($ast, Class_::class)];
    }

    public function rewrite(Node $node): void
    {
        if (!$node instanceof Class_) {
            return;
        }

        $this->changeExtendedClass($node);
    }

    private function changeExtendedClass(Class_ $node): void
    {
        if ($node->extends->toString() === 'ObjectBehavior') {
            $node->extends = new Node\Name('TestCase');
        }
    }
}
