<?php

namespace Transunit\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class TestSubjectCallVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node)
    {
        if (! $node instanceof Node\Expr\MethodCall) {
            return $node;
        }

        if (!$node->var instanceof Node\Expr\Variable) {
            return $node;
        }

        if ('this' !== $node->var->name) {
            return $node;
        }

        if ('prophesize' === $node->name->toString()) {
            return $node;
        }

        if ('beConstructedWith' === $node->name->toString()) {
            return $node;
        }

        if ('beConstructedThrough' === $node->name->toString()) {
            return $node;
        }

        $node->var = new Node\Expr\PropertyFetch(
            new Node\Expr\Variable('this'),
            '_testSubject'
        );

        return $node;
    }
}
