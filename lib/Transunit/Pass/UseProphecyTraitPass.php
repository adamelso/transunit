<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\Node\Stmt\Class_;
use Transunit\Pass;

/**
 * ```
 *   class TestSubjectTest extends TestCase
 *   {
 * +     use ProphecyTrait;
 * ```
 */
class UseProphecyTraitPass implements Pass
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

        $this->useProphecyTrait($node);
    }

    private function useProphecyTrait(Class_ $node):  void
    {
        $node->stmts = array_merge(
            [new Node\Stmt\TraitUse([new Node\Name('ProphecyTrait')])],
            $node->stmts
        );
    }
}
