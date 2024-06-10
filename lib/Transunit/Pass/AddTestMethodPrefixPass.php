<?php

namespace Transunit\Pass;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\NodeFinder;
use Transunit\Pass;

/**
 * ```
 * - function it_handles_kernel_request_events()
 * + public function test_it_handles_kernel_request_events(): void
 *   {
 * ```
 */
class AddTestMethodPrefixPass implements Pass
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

        $this->addPrefix($node);
    }

    /**
     * public function test_it_should_do_something(): void { ... }
     */
    private function addPrefix(Node\Stmt\ClassMethod $node): void
    {
        $methodName = $node->name->toString();

        if (0 !== strpos($methodName, 'it_')) {
            return;
        }

        $node->name = new Node\Identifier('test_' . $methodName);
        $node->flags = Modifiers::PUBLIC;
        $node->returnType = new Node\Name('void');
    }
}
