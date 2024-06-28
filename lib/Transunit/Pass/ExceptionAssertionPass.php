<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\NodeFinder;
use Transunit\Pass;

/**
 * ```
 *   public function test_it_throws_an_exception_if_country_name_cannot_be_converted_to_code(): void
 *   {
 * -     $this->shouldThrow(\InvalidArgumentException::class)->during('convertToCode', ['Atlantis']);
 * +     static::expectException(\InvalidArgumentException::class);
 * +     $this->_testSubject->convertToCode('Atlantis');
 *   }
 * ```
 */
class ExceptionAssertionPass implements Pass
{
    public function find(NodeFinder $nodeFinder, $ast): array
    {
        return $nodeFinder->find($ast, function (Node $node) {
            if ($node instanceof Node\Stmt\ClassMethod && !in_array($node->name->toString(), ['setUp', 'let'], true)) {
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

        $this->expectException($node);
    }

    private function expectException(Node\Stmt\ClassMethod $node): void
    {
        $newStmts = [];

        foreach ($node->stmts as $stmt) {
            $newStmts[] = $stmt;

            if (! $stmt instanceof Node\Stmt\Expression) {
                continue;
            }

            $expression = $stmt;

            if (! $expression->expr instanceof Node\Expr\MethodCall) {
                continue;
            }

            if ('during' !== $expression->expr->name->toString()) {
                continue;
            }

            if (! $expression->expr->var instanceof Node\Expr\MethodCall) {
                continue;
            }

            if ('shouldThrow' !== $expression->expr->var->name->toString()) {
                continue;
            }

            array_pop($newStmts);

            $newStmts[] = new Node\Stmt\Expression(
                new Node\Expr\MethodCall(
                    new Node\Expr\Variable('this'),
                    'expectException',
                    $expression->expr->var->args
                )
            );

            $subjectMethodName = $expression->expr->args[0]->value->value;
            $subjectMethodArgs = [];

            if (count($expression->expr->args) > 1 && $expression->expr->args[1]->value instanceof Node\Expr\Array_) {
                /** @var Node\ArrayItem $item */
                foreach ($expression->expr->args[1]->value->items as $item) {
                    $subjectMethodArgs[] = new Node\Arg($item->value);
                }
            }

            $newStmts[] = new Node\Stmt\Expression(
                new Node\Expr\MethodCall(
                    new Node\Expr\PropertyFetch(
                        new Node\Expr\Variable('this'),
                        '_testSubject'
                    ),
                    $subjectMethodName,
                    $subjectMethodArgs
                )
            );
        }

        $node->stmts = $newStmts;
    }
}
