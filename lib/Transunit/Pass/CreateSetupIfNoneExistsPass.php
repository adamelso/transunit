<?php

namespace Transunit\Pass;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\NodeFinder;
use Transunit\Pass;

/**
 * ```
 *   class TestSubjectTest extends TestCase
 *   {
 * +     protected function setUp(): void
 * +     {
 * +         $this->_testSubject = new TestSubject();
 * +     }
 * ```
 */
class CreateSetupIfNoneExistsPass implements Pass
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

        foreach ($node->stmts as $stmt) {
            if (
                $stmt instanceof Node\Stmt\ClassMethod
                && in_array($stmt->name->toString(), ['setUp', 'let'], true)
            ) {
                return;
            }
        }

        $useProphecyTrait = array_shift($node->stmts);

        // Add setUp method
        $setupMethod = new Node\Stmt\ClassMethod('setUp', [
            'type' => Modifiers::PROTECTED,
            'stmts' => [
                new Node\Stmt\Expression(
                    new Node\Expr\Assign(
                        new Node\Expr\PropertyFetch(
                            new Node\Expr\Variable('this'),
                            '_testSubject'
                        ),
                        new Node\Expr\New_(
                            new Node\Name($subjectClassname)
                        )
                    )
                ),
            ],
            'returnType' => new Node\Name('void')
        ]);

        array_unshift($node->stmts, $setupMethod);
        array_unshift($node->stmts, $useProphecyTrait);
    }
}
