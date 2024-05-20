<?php

namespace Transunit\Pass;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\NodeFinder;
use Transunit\Pass;

class GlobalTestSubjectInstancePass implements Pass
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

        $testClassname = $node->name->toString();
        $subjectClassname = substr($testClassname, 0, -4);

        $setupMethod = null;
        foreach ($node->stmts as $stmt) {
            if (
                $stmt instanceof Node\Stmt\ClassMethod
                && in_array($stmt->name->toString(), ['setUp', 'let'], true)
            ) {
                $setupMethod = $stmt;
                break;
            }
        }

        $useProphecyTrait = array_shift($node->stmts);

        if (!$setupMethod) {
            // Add setUp method
            $setupMethod = new Node\Stmt\ClassMethod('setUp', [
                'type' => Modifiers::PROTECTED,
                'stmts' => [
                    $this->writeInstantiation($subjectClassname),
                ],
                'returnType' => new Node\Name('void')
            ]);

            array_unshift($node->stmts, $setupMethod);
        }

        $this->instantiateTestSubject($setupMethod, $subjectClassname);
        $this->declareTestSubjectAsClassProperty($node, $subjectClassname);

        array_unshift($node->stmts, $useProphecyTrait);
    }

    private function declareTestSubjectAsClassProperty(Node\Stmt\Class_ $node, string $subjectClassname): void
    {
        $testSubjectProperty = new Node\Stmt\Property(
            Modifiers::PRIVATE,
            [new Node\Stmt\PropertyProperty('_testSubject')],
            [],
            new Node\Name($subjectClassname)
        );

        array_unshift($node->stmts, $testSubjectProperty);
    }

    private function instantiateTestSubject(Node\Stmt\ClassMethod $node, string $subjectClassname): void
    {
        $collaboratorNames = [];

        foreach ($node->stmts as $stmt) {
            // Check if expression is a property assignment
            if ($stmt instanceof Node\Stmt\Expression
                && $stmt->expr instanceof Node\Expr\Assign
                && $stmt->expr->var instanceof Node\Expr\PropertyFetch
                && $stmt->expr->var->var instanceof Node\Expr\Variable
                && $stmt->expr->var->var->name === 'this'
            ) {
                $collaboratorNames[$stmt->expr->var->name->name] = true;
            }
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
