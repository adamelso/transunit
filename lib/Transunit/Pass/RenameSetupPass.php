<?php

namespace Transunit\Pass;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\NodeFinder;
use Transunit\Pass;

/**
 * ```
 *   class TestSubjectTest extends TestCase
 *   {
 *       // ...
 *
 * -     function let()
 * +     protected function setUp(): void
 *       {
 * ```
 */
class RenameSetupPass implements Pass
{
    public function find(NodeFinder $nodeFinder, $ast): array
    {
        return $nodeFinder->find($ast, function (Node $node) {
            if ($node instanceof Node\Stmt\ClassMethod && !in_array($node->name->toString(), ['setUp', 'let'],true)) {
                return $node;
            }

            return null;
        });
    }

    public function rewrite(Node $node): void
    {
        if (!$node instanceof Node\Stmt\ClassMethod) {
            return;
        }

        $this->renameSetup($node);
    }

    /**
     * protected function setUp(): void { ... }
     */
    private function renameSetup(Node\Stmt\ClassMethod $node): void
    {
        if ($node->name->toString() !== 'let') {
            return;
        }

        $node->name = new Node\Identifier('setUp');
        $node->flags = Modifiers::PROTECTED;
    }
}
