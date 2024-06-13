<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\NodeFinder;
use Transunit\Pass;

class ReplaceCallsToGetWrappedObjectPass implements Pass
{
    public function find(NodeFinder $nodeFinder, $ast): array
    {
        return $nodeFinder->find($ast, function (Node $node) {
            if (
                $node instanceof Node\Expr\MethodCall
                && 'getWrappedObject' === $node->name->toString()
            ) {
                return $node;
            }

            return null;
        });
    }

    public function rewrite(Node $node): void
    {
        if (! $node instanceof Node\Expr\MethodCall) {
            return;
        }

        $node->name->name = 'reveal';
    }
}