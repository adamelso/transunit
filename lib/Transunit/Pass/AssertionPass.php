<?php

namespace Transunit\Pass;

use PhpParser\NodeFinder;
use PhpParser\Node;
use Transunit\Pass;

class AssertionPass implements Pass
{
    public function find(NodeFinder $nodeFinder, $ast): array
    {
        return $nodeFinder->find($ast, function (Node $node) {
            if ($node instanceof Node\Stmt\Expression) {
                return $node;
            }

            return null;
        });
    }

    public function rewrite(Node $node): void
    {
        if (!$node->expr instanceof Node\Expr\MethodCall) {
            return;
        }

        $assertion = $node->expr->name->toString();
        $mappedAssertions = [
            'shouldBe' => 'assertSame',
            'shouldReturn' => 'assertSame',
            'shouldBeLike' => 'assertEquals',
            'shouldHaveCount' => 'assertCount',
            'shouldHaveType' => 'assertInstanceOf',
            'shouldImplement' => 'assertInstanceOf',
        ];

        if (!isset($mappedAssertions[$assertion])) {
            return;
        }

        $expectation = $node->expr->args[0]->value;
        $call = $node->expr->var;

        if (
            $expectation instanceof Node\Expr\ConstFetch
            && $expectation->name->toString() === 'null'
        ) {
            // static::assertNull($call);
            $rewrittenAssertion = new Node\Expr\StaticCall(
                new Node\Name('static'),
                'assertNull',
                [
                    new Node\Arg($call)
                ]
            );

        } else {
            // static::assertSame($expectation, $call);
            $rewrittenAssertion = new Node\Expr\StaticCall(
                new Node\Name('static'),
                $mappedAssertions[$assertion],
                [
                    new Node\Arg($expectation),
                    new Node\Arg($call)
                ]
            );
        }

        $node->expr = $rewrittenAssertion;
    }
}
