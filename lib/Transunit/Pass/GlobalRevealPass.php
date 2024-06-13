<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\NodeFinder;
use Transunit\Pass;

class GlobalRevealPass implements Pass
{
    public function find(NodeFinder $nodeFinder, $ast): array
    {
        return [$nodeFinder->findFirstInstanceOf($ast, Node\Stmt\Class_::class)];
    }

    public function rewrite(Node $node): void
    {
        if (!$node instanceof Node\Stmt\Class_) {
            return;
        }

        $setupMethod = $this->findSetupMethod($node);

        if (!$setupMethod instanceof Node\Stmt\ClassMethod) {
            return;
        }

        $useProphecyTrait = array_shift($node->stmts);

        $testClassname = $node->name->toString();
        $subjectClassname = substr($testClassname, 0, -4);

        $this->instantiateTestSubject($setupMethod, $subjectClassname);

        array_unshift($node->stmts, $useProphecyTrait);
    }

    private function findSetupMethod(Node\Stmt\Class_ $node): ?Node\Stmt\ClassMethod
    {
        foreach ($node->stmts as $stmt) {
            if (
                $stmt instanceof Node\Stmt\ClassMethod
                && in_array($stmt->name->toString(), ['setUp', 'let'], true)
            ) {
                return $stmt;
            }
        }

        return null;
    }

    private function instantiateTestSubject(Node\Stmt\ClassMethod $node, string $subjectClassname): void
    {
        $rewrittenNode = null;

        foreach ($node->stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\Expression) {
                continue;
            }

            if (
                $stmt->expr instanceof Node\Expr\Assign
                && $stmt->expr->expr instanceof Node\Expr\New_
                && $stmt->expr->var instanceof Node\Expr\PropertyFetch
                && $stmt->expr->var->name instanceof Node\Identifier
                && '_testSubject' === $stmt->expr->var->name->name
            ) {
                $rewrittenNode = $stmt->expr->expr;
                break;
            }

            if (
                $stmt->expr instanceof Node\Expr\MethodCall
                && $stmt->expr->name instanceof Node\Identifier
                && 'beConstructedWith' === $stmt->expr->name->name
            ) {
                $rewrittenNode = $stmt->expr;
                break;
            }
        }

        $newArgs = [];

        foreach ($rewrittenNode->args as $arg) {
            if (!$arg->value instanceof Node\Expr\Variable) {
                $newArgs[] = $arg;
                continue;
            }

            // @todo Confirm the variable was declared as an argument of the let() method

            $newArgs[] = new Node\Arg(new Node\Expr\MethodCall(
                new Node\Expr\PropertyFetch(
                    new Node\Expr\Variable('this'),
                    $arg->value->name
                ),
                'reveal'
            ));
        }

        $rewrittenNode->args = $newArgs;
    }
}
