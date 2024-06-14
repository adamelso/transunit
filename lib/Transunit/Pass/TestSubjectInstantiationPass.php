<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\NodeFinder;
use Transunit\Pass;

/**
 * ```
 *       function let(AgentRepository $agentRepository, EventDispatcher $eventDispatcher)
 *       {
 * -         $this->beConstructedWith($agentRepository, $eventDispatcher, 'chicken');
 * +         $this->_testSubject = new TestSubject($this->agentRepository->reveal(), $this->eventDispatcher->reveal(), 'chicken');
 *       }
 * ```
 */
class TestSubjectInstantiationPass implements Pass
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
        /** @var null|Node\Arg[] $args */
        $args = null;

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
                // Already instantiated.
                continue;
            }

            if (
                $stmt->expr instanceof Node\Expr\MethodCall
                && $stmt->expr->name instanceof Node\Identifier
                && 'beConstructedWith' === $stmt->expr->name->name
            ) {
                $args = $stmt->expr->args;
                break;
            }
        }

        $node->stmts = array_map(function ($stmt) use ($subjectClassname, $args) {
            if ($stmt instanceof Node\Stmt\Expression
                && $stmt->expr instanceof Node\Expr\MethodCall
                && $stmt->expr->var instanceof Node\Expr\Variable
                && $stmt->expr->var->name === 'this'
                && $stmt->expr->name->toString() === 'beConstructedWith'
            ) {
                // replace $this->beConstructedWith(...) with $this->_testSubject = new TestSubject(...)
                return $this->writeInstantiation($subjectClassname, $args);
            }

            return $stmt;
        }, $node->stmts);

        if (null === $args) {
            array_unshift($node->stmts, $this->writeInstantiation($subjectClassname));
        }
    }

    private function writeInstantiation(string $subjectClassname, ?array $args = null): Node\Stmt\Expression
    {
        $newInstance = null === $args
            ? new Node\Expr\New_(new Node\Name($subjectClassname))
            : new Node\Expr\New_(new Node\Name($subjectClassname), $args);

        return new Node\Stmt\Expression(
            new Node\Expr\Assign(
                new Node\Expr\PropertyFetch(
                    new Node\Expr\Variable('this'),
                    '_testSubject'
                ),
                $newInstance
            )
        );
    }
}
