<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\NodeFinder;
use Transunit\Pass;

/**
 * ```
 * - $collaborator->call($this)->shouldBeCalled();
 * + $collaborator->call($this->_testSubject)->shouldBeCalled();
 * ```
 */
class TestSubjectAsArgumentPass implements Pass
{
    public function find(NodeFinder $nodeFinder, $ast): array
    {
        return $nodeFinder->find($ast, function (Node $node) {
            if (
                $node instanceof Node\Arg
                && $node->value instanceof Node\Expr\Variable
                && 'this' === $node->value->name
            ) {
                return $node;
            }

            return null;
        });
    }

    public function rewrite(Node $node): void
    {
        if (! $node instanceof Node\Arg) {
            return;
        }

        $node->value = new Node\Expr\PropertyFetch(
            new Node\Expr\Variable('this'),
            '_testSubject'
        );
    }
}
