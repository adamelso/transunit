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
        if (! $node->expr instanceof Node\Expr\MethodCall) {
            return;
        }

        $assertion = $node->expr->name->toString();

        if ('willReturn' === $assertion) {
            return;
        }

        if ('during' === $assertion) {
            return;
        }

        if ('shouldBeCalled' === $assertion) {
            return;
        }

        if ('shouldNotBeCalled' === $assertion) {
            return;
        }

        if ('shouldBeCalledTimes' === $assertion) {
            return;
        }

        if (! str_starts_with($assertion, 'should')) {
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

        $mappedDynamicAssertions = [];

        $impliedMethodName = null;

        if (! isset($mappedAssertions[$assertion])) {
            if (str_starts_with($assertion, 'shouldBe') || str_starts_with($assertion, 'shouldHave')) {
                $impliedMethodName = strtr($assertion, [
                    'shouldBe' => 'is',
                    'shouldHave' => 'has',
                ]);
                $mappedDynamicAssertions[$assertion] = 'assertTrue';

            } elseif (str_starts_with($assertion, 'shouldNotBe') || str_starts_with($assertion, 'shouldNotHave')) {
                $impliedMethodName = strtr($assertion, [
                    'shouldNotBe' => 'is',
                    'shouldNotHave' => 'has',
                ]);
                $mappedDynamicAssertions[$assertion] = 'assertFalse';
            }
        }

        if (null === $impliedMethodName && count($node->expr->args) > 0) {
            $call = $node->expr->var;
            $expectation = $node->expr->args[0]->value;
        } else {
            $call = new Node\Expr\MethodCall($node->expr->var, $impliedMethodName);
            $expectation = null;
        }

        if (
            $expectation instanceof Node\Expr\ConstFetch
            && isset($mappedConstantAssertions[$expectation->name->toString()])
        ) {
            $assertionMethod = $mappedConstantAssertions[$expectation->name->toString()];

            $rewrittenAssertion = new Node\Expr\StaticCall(
                new Node\Name('self'),
                $assertionMethod,
                [
                    new Node\Arg($call)
                ]
            );
        } elseif (
            null === $expectation
            && isset($mappedDynamicAssertions[$assertion])
        ) {
            $assertionMethod = $mappedDynamicAssertions[$assertion];

            $rewrittenAssertion = new Node\Expr\StaticCall(
                new Node\Name('self'),
                $assertionMethod,
                [
                    new Node\Arg($call)
                ]
            );
        } else {
            $assertionMethod = $mappedAssertions[$assertion] ?? null;

            if (null === $assertionMethod) {
                throw new \BadMethodCallException("PhpSpec assertion $assertion is not mapped to any PHPUnit assertion.");
            }

            // e.g. static::assertSame($expectation, $call);
            $rewrittenAssertion = new Node\Expr\StaticCall(
                new Node\Name('self'),
                $assertionMethod,
                [
                    new Node\Arg($expectation),
                    new Node\Arg($call)
                ]
            );
        }

        $node->expr = $rewrittenAssertion;
    }
}
