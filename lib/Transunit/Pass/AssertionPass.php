<?php

namespace Transunit\Pass;

use PhpParser\NodeFinder;
use PhpParser\Node;
use Transunit\Pass;

/**
 * ```
 * -   $this->_testSubject->contractOut(47)->shouldReturn($agent47);
 * +   self::assertSame($agent47, $this->_testSubject->contractOut(47));
 * ```
 */
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

        $mappedAssertions = [
            'shouldBe' => 'assertSame',
            'shouldReturn' => 'assertSame',
            'shouldBeLike' => 'assertEquals',
            'shouldHaveCount' => 'assertCount',
            'shouldHaveType' => 'assertInstanceOf',
            'shouldImplement' => 'assertInstanceOf',
        ];

        $mappedConstantAssertions = [
            'null' => 'assertNull',
            'true' => 'assertTrue',
            'false' => 'assertFalse',
        ];

        $assertion = $node->expr->name->toString();

        if (!isset($mappedAssertions[$assertion])) {
            return;
        }

        $expectation = $node->expr->args[0]->value;
        $call = $node->expr->var;

        if (
            $expectation instanceof Node\Expr\ConstFetch
            && isset($mappedConstantAssertions[$expectation->name->toString()])
        ) {
            $assertionMethod = $mappedConstantAssertions[$expectation->name->toString()] ?? null;

            $rewrittenAssertion = new Node\Expr\StaticCall(
                new Node\Name('self'),
                $assertionMethod,
                [
                    new Node\Arg($call)
                ]
            );
        } else {
            // static::assertSame($expectation, $call);
            $rewrittenAssertion = new Node\Expr\StaticCall(
                new Node\Name('self'),
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
