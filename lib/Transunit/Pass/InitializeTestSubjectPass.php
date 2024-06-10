<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\NodeFinder;
use Transunit\Pass;

/**
 * ```
 *   class TestSubjectTest extends TestCase
 *   {
 *       use ProphecyTrait;
 *
 *       private TestSubject $_testSubject;
 *
 * -     function let(AgentRepository $agentRepository, EventDispatcher $eventDispatcher)
 * +     function let()
 *       {
 * -         $this->beConstructedWith($agentRepository, $eventDispatcher);
 * +         $this->_testSubject = new TestSubject($this->agentRepository->reveal(), $this->eventDispatcher->reveal());
 *       }
 * ```
 */
class InitializeTestSubjectPass implements Pass
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

        $this->instantiateTestSubject($setupMethod);

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

    private function instantiateTestSubject(Node\Stmt\ClassMethod $node): void
    {
        $testClassname = $node->name->toString();
        $subjectClassname = substr($testClassname, 0, -4);

        $collaboratorNames = [];

        foreach ($node->stmts as $stmt) {
            // Check if expression is a property assignment
            if (! $stmt instanceof Node\Stmt\Expression) {
                continue;
            }

            if (! $stmt->expr instanceof Node\Expr\Assign) {
                continue;
            }

            if (! $stmt->expr->var instanceof Node\Expr\PropertyFetch) {
                continue;
            }

            if (! $stmt->expr->var->var instanceof Node\Expr\Variable) {
                continue;
            }

            $collaboratorVariableName = $stmt->expr->var->var->name;

            if ($collaboratorVariableName === 'this') {
                continue;
            }

            $collaboratorNames[$collaboratorVariableName] = true;
        }

        $globalCollaborators = [];

        foreach ($collaboratorNames as $collaborator => $v) {
            // call $this->{$collaborator}->reveal()
            $globalCollaborators[] = new Node\Arg(new Node\Expr\MethodCall(
                new Node\Expr\PropertyFetch(
                    new Node\Expr\Variable('this'),
                    $collaborator
                ),
                'reveal'
            ));
        }

        $node->stmts = array_map(function ($stmt) use ($subjectClassname, $globalCollaborators) {
            if ($stmt instanceof Node\Stmt\Expression
                && $stmt->expr instanceof Node\Expr\MethodCall
                && $stmt->expr->var instanceof Node\Expr\Variable
                && $stmt->expr->var->name === 'this'
                && $stmt->expr->name->toString() === 'beConstructedWith'
            ) {
                // replace $this->beConstructedWith(...) with $this->_testSubject = new TestSubject(...)
                return $this->writeInstantiation($subjectClassname, $globalCollaborators);
            }

            return $stmt;
        }, $node->stmts);
    }

    private function writeInstantiation(string $subjectClassname, array $globalCollaborators = []): Node\Stmt\Expression
    {
        return new Node\Stmt\Expression(
            new Node\Expr\Assign(
                new Node\Expr\PropertyFetch(
                    new Node\Expr\Variable('this'),
                    '_testSubject'
                ),
                new Node\Expr\New_(
                    new Node\Name($subjectClassname),
                    $globalCollaborators
                )
            )
        );
    }
}
